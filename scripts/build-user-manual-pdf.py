#!/usr/bin/env python3
"""Build the user manual PDF without external Python packages.

The local environment does not include pandoc/wkhtmltopdf/weasyprint, so this
small renderer turns the Markdown manual into a clean, printable PDF using
standard PDF fonts and the existing brand image.
"""

from __future__ import annotations

import re
import shutil
import subprocess
import tempfile
from dataclasses import dataclass, field
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANUAL = ROOT / "docs" / "user-manual.md"
OUTPUT = ROOT / "docs" / "user-manual.pdf"
LOGO = ROOT / "public" / "brand" / "ykj-logo-clean.png"

PAGE_WIDTH = 595.0
PAGE_HEIGHT = 842.0
MARGIN_X = 50.0
TOP_Y = 760.0
BOTTOM_Y = 58.0

COLORS = {
    "ink": (37 / 255, 43 / 255, 82 / 255),
    "muted": (96 / 255, 105 / 255, 135 / 255),
    "faint": (142 / 255, 150 / 255, 178 / 255),
    "orange": (1.0, 136 / 255, 92 / 255),
    "blue": (103 / 255, 114 / 255, 255 / 255),
    "aqua": (122 / 255, 215 / 255, 208 / 255),
    "paper": (247 / 255, 248 / 255, 1.0),
    "panel": (1.0, 1.0, 1.0),
    "line": (211 / 255, 216 / 255, 244 / 255),
    "soft_blue": (237 / 255, 240 / 255, 1.0),
    "soft_aqua": (235 / 255, 249 / 255, 248 / 255),
    "soft_orange": (1.0, 243 / 255, 235 / 255),
}


def pdf_escape(value: str) -> str:
    return value.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")


def clean_markdown(value: str) -> str:
    value = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r"\1 (\2)", value)
    value = value.replace("**", "")
    value = value.replace("`", "")
    value = value.replace("<br>", " ")
    value = value.replace("&nbsp;", " ")
    return value.strip()


def text_width(text: str, size: float, font: str = "F1") -> float:
    factor = 0.6 if font == "F3" else 0.53
    if font == "F2":
        factor = 0.56
    return len(text) * size * factor


def wrap_text(text: str, width: float, size: float, font: str = "F1") -> list[str]:
    words = clean_markdown(text).split()
    if not words:
        return []

    lines: list[str] = []
    current = words[0]
    for word in words[1:]:
        candidate = f"{current} {word}"
        if text_width(candidate, size, font) <= width:
            current = candidate
        else:
            lines.append(current)
            current = word
    lines.append(current)
    return lines


def jpeg_size(data: bytes) -> tuple[int, int]:
    index = 2
    while index < len(data):
        if data[index] != 0xFF:
            index += 1
            continue
        marker = data[index + 1]
        index += 2
        if marker in (0xD8, 0xD9):
            continue
        length = int.from_bytes(data[index : index + 2], "big")
        if 0xC0 <= marker <= 0xC3:
            height = int.from_bytes(data[index + 3 : index + 5], "big")
            width = int.from_bytes(data[index + 5 : index + 7], "big")
            return width, height
        index += length
    raise ValueError("Could not read JPEG dimensions.")


def make_logo_jpeg() -> tuple[bytes, int, int] | None:
    if not LOGO.exists() or shutil.which("sips") is None:
        return None

    with tempfile.TemporaryDirectory() as directory:
        output = Path(directory) / "ykj-logo.jpg"
        subprocess.run(
            ["sips", "-s", "format", "jpeg", str(LOGO), "--out", str(output)],
            check=True,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        data = output.read_bytes()
        width, height = jpeg_size(data)
        return data, width, height


@dataclass
class Page:
    show_header: bool = True
    commands: list[str] = field(default_factory=list)

    def rgb(self, color: tuple[float, float, float], stroke: bool = False) -> None:
        op = "RG" if stroke else "rg"
        self.commands.append(f"{color[0]:.4f} {color[1]:.4f} {color[2]:.4f} {op}")

    def rect(
        self,
        x: float,
        y: float,
        width: float,
        height: float,
        fill: tuple[float, float, float] | None = None,
        stroke: tuple[float, float, float] | None = None,
        stroke_width: float = 1.0,
    ) -> None:
        if fill:
            self.rgb(fill)
            self.commands.append(f"{x:.2f} {y:.2f} {width:.2f} {height:.2f} re f")
        if stroke:
            self.rgb(stroke, stroke=True)
            self.commands.append(f"{stroke_width:.2f} w")
            self.commands.append(f"{x:.2f} {y:.2f} {width:.2f} {height:.2f} re S")

    def line(
        self,
        x1: float,
        y1: float,
        x2: float,
        y2: float,
        color: tuple[float, float, float] = COLORS["line"],
        width: float = 1.0,
    ) -> None:
        self.rgb(color, stroke=True)
        self.commands.append(f"{width:.2f} w {x1:.2f} {y1:.2f} m {x2:.2f} {y2:.2f} l S")

    def text(
        self,
        x: float,
        y: float,
        value: str,
        size: float = 10.5,
        font: str = "F1",
        color: tuple[float, float, float] = COLORS["ink"],
    ) -> None:
        self.rgb(color)
        escaped = pdf_escape(value)
        self.commands.append(f"BT /{font} {size:.2f} Tf {x:.2f} {y:.2f} Td ({escaped}) Tj ET")

    def image(self, name: str, x: float, y: float, width: float, height: float) -> None:
        self.commands.append(f"q {width:.2f} 0 0 {height:.2f} {x:.2f} {y:.2f} cm /{name} Do Q")


class ManualPdf:
    def __init__(self, logo: tuple[bytes, int, int] | None) -> None:
        self.logo = logo
        self.pages: list[Page] = []
        self.page = Page(show_header=False)
        self.pages.append(self.page)
        self.y = TOP_Y

    def new_page(self, show_header: bool = True) -> None:
        self.page = Page(show_header=show_header)
        self.pages.append(self.page)
        self.y = TOP_Y

    def ensure(self, height: float) -> None:
        if self.y - height < BOTTOM_Y:
            self.new_page()

    def cover(self) -> None:
        page = self.page
        page.rect(0, 0, PAGE_WIDTH, PAGE_HEIGHT, fill=COLORS["soft_blue"])
        page.rect(46, 76, 503, 690, fill=(1, 1, 1), stroke=COLORS["line"])

        if self.logo:
            page.image("ImLogo", 224, 566, 148, 148)

        page.text(76, 510, "YOUR KAYAKING JOURNAL", 11, "F3", COLORS["orange"])
        page.text(76, 466, "User Manual", 36, "F2", COLORS["ink"])
        page.text(76, 438, "Private beta guide for logging, importing, planning, and reviewing paddles.", 13, "F1", COLORS["muted"])
        page.text(76, 408, "Last updated: 21 April 2026", 10.5, "F1", COLORS["faint"])

        cards = [
            ("LOG", "Add sessions manually or from Garmin files."),
            ("MAP", "See routes, places, and expedition pins."),
            ("REFLECT", "Use observations to capture lessons learned."),
            ("PLAN", "Sketch future paddles with area forecasts."),
        ]
        x_positions = [76, 304, 76, 304]
        y_positions = [296, 296, 188, 188]
        for (label, body), x, y in zip(cards, x_positions, y_positions):
            page.rect(x, y, 188, 80, fill=COLORS["paper"], stroke=COLORS["line"])
            page.text(x + 16, y + 52, label, 9, "F3", COLORS["orange"])
            for offset, line in enumerate(wrap_text(body, 150, 10.5)):
                page.text(x + 16, y + 30 - offset * 14, line, 10.5, "F1", COLORS["ink"])

    def at_a_glance(self) -> None:
        self.new_page()
        self.heading(2, "At a glance")
        self.paragraph(
            "The app is built around one simple loop: plan the paddle, paddle the route, log what happened, and review what to improve next time."
        )

        page = self.page
        top = self.y - 20
        steps = [
            ("1", "Plan", "Sketch route and check area forecast"),
            ("2", "Paddle", "Record with Garmin or note manually"),
            ("3", "Log", "Add files, conditions, gear, and pins"),
            ("4", "Reflect", "Write observations and improvements"),
            ("5", "Review", "Use Dashboard, Diary, Library, maps"),
        ]
        x = 58
        for number, title, body in steps:
            page.rect(x, top - 112, 92, 100, fill=COLORS["paper"], stroke=COLORS["line"])
            page.text(x + 12, top - 42, number, 20, "F2", COLORS["blue"])
            page.text(x + 12, top - 64, title, 12, "F2", COLORS["ink"])
            for offset, line in enumerate(wrap_text(body, 68, 8.8)):
                page.text(x + 12, top - 82 - offset * 11, line, 8.8, "F1", COLORS["muted"])
            if number != "5":
                page.line(x + 94, top - 62, x + 106, top - 62, COLORS["orange"], 1.4)
            x += 98

        self.y = top - 152
        self.visual_panel(
            "Map logic",
            [
                ("Track lines", "Need GPX, FIT, or manual route points."),
                ("I paddled here", "Needs at least one coordinate; works for normal sessions too."),
                ("Expedition map", "Only expedition-tagged sessions appear here."),
            ],
        )

    def visual_panel(self, title: str, items: list[tuple[str, str]]) -> None:
        self.ensure(138)
        x = MARGIN_X
        width = PAGE_WIDTH - MARGIN_X * 2
        height = 120
        y = self.y - height
        self.page.rect(x, y, width, height, fill=COLORS["soft_aqua"], stroke=COLORS["line"])
        self.page.text(x + 18, y + height - 28, title.upper(), 9, "F3", COLORS["orange"])
        col_width = (width - 54) / len(items)
        for index, (label, body) in enumerate(items):
            cx = x + 18 + index * (col_width + 9)
            self.page.rect(cx, y + 18, col_width, 62, fill=(1, 1, 1), stroke=COLORS["line"])
            self.page.text(cx + 10, y + 55, label, 10.5, "F2", COLORS["ink"])
            for offset, line in enumerate(wrap_text(body, col_width - 20, 8.8)):
                self.page.text(cx + 10, y + 38 - offset * 11, line, 8.8, "F1", COLORS["muted"])
        self.y = y - 22

    def heading(self, level: int, text: str) -> None:
        text = clean_markdown(text)
        if level == 2:
            self.ensure(58)
            self.y -= 12
            self.page.text(MARGIN_X, self.y, text, 19, "F2", COLORS["ink"])
            self.y -= 16
            self.page.line(MARGIN_X, self.y, PAGE_WIDTH - MARGIN_X, self.y, COLORS["line"], 1)
            self.y -= 18
        elif level == 3:
            self.ensure(40)
            self.y -= 10
            self.page.text(MARGIN_X, self.y, text, 13.5, "F2", COLORS["ink"])
            self.y -= 18
        else:
            self.ensure(48)
            self.page.text(MARGIN_X, self.y, text, 24, "F2", COLORS["ink"])
            self.y -= 30

    def paragraph(self, text: str) -> None:
        lines = wrap_text(text, PAGE_WIDTH - MARGIN_X * 2, 10.5)
        if not lines:
            return
        self.ensure(len(lines) * 15 + 10)
        for line in lines:
            self.page.text(MARGIN_X, self.y, line, 10.5, "F1", COLORS["muted"])
            self.y -= 15
        self.y -= 6

    def bullet(self, text: str, ordered: str | None = None) -> None:
        lines = wrap_text(text, PAGE_WIDTH - MARGIN_X * 2 - 28, 10.2)
        self.ensure(max(1, len(lines)) * 14 + 4)
        marker = f"{ordered}." if ordered else "-"
        self.page.text(MARGIN_X + 2, self.y, marker, 10.2, "F2", COLORS["orange"])
        for index, line in enumerate(lines):
            self.page.text(MARGIN_X + 24, self.y - index * 14, line, 10.2, "F1", COLORS["ink"])
        self.y -= max(1, len(lines)) * 14 + 3

    def table(self, rows: list[list[str]]) -> None:
        if not rows:
            return
        columns = max(len(row) for row in rows)
        x = MARGIN_X
        width = PAGE_WIDTH - MARGIN_X * 2
        col_widths = [width / columns] * columns
        if columns == 2:
            col_widths = [128, width - 128]

        for row_index, row in enumerate(rows):
            cell_lines = [
                wrap_text(row[col] if col < len(row) else "", col_widths[col] - 14, 9.2, "F2" if row_index == 0 else "F1")
                for col in range(columns)
            ]
            height = max(28, 15 + max(len(lines) for lines in cell_lines) * 12)
            self.ensure(height + 8)
            y = self.y - height
            fill = COLORS["soft_blue"] if row_index == 0 else ((1, 1, 1) if row_index % 2 else COLORS["paper"])
            self.page.rect(x, y, width, height, fill=fill, stroke=COLORS["line"])

            cursor_x = x
            for col, lines in enumerate(cell_lines):
                if col > 0:
                    self.page.line(cursor_x, y, cursor_x, y + height, COLORS["line"], 0.6)
                for line_index, line in enumerate(lines):
                    self.page.text(
                        cursor_x + 7,
                        y + height - 18 - line_index * 12,
                        line,
                        9.2,
                        "F2" if row_index == 0 else "F1",
                        COLORS["ink"] if row_index == 0 else COLORS["muted"],
                    )
                cursor_x += col_widths[col]
            self.y = y
        self.y -= 14

    def code_block(self, lines: list[str]) -> None:
        height = len(lines) * 12 + 20
        self.ensure(height)
        y = self.y - height
        self.page.rect(MARGIN_X, y, PAGE_WIDTH - MARGIN_X * 2, height, fill=(0.95, 0.96, 0.99), stroke=COLORS["line"])
        for index, line in enumerate(lines):
            self.page.text(MARGIN_X + 12, self.y - 18 - index * 12, line[:86], 8.8, "F3", COLORS["ink"])
        self.y = y - 12

    def parse_markdown(self, lines: list[str]) -> None:
        paragraph: list[str] = []
        index = 0

        def flush_paragraph() -> None:
            nonlocal paragraph
            if paragraph:
                self.paragraph(" ".join(paragraph))
                paragraph = []

        while index < len(lines):
            raw = lines[index].rstrip()
            line = raw.strip()

            if not line:
                flush_paragraph()
                index += 1
                continue

            if line.startswith("```"):
                flush_paragraph()
                index += 1
                code_lines: list[str] = []
                while index < len(lines) and not lines[index].strip().startswith("```"):
                    code_lines.append(lines[index].rstrip())
                    index += 1
                self.code_block(code_lines)
                index += 1
                continue

            if line.startswith("|"):
                flush_paragraph()
                table_rows: list[list[str]] = []
                while index < len(lines) and lines[index].strip().startswith("|"):
                    row = [cell.strip() for cell in lines[index].strip().strip("|").split("|")]
                    if not all(set(cell) <= {"-", ":", " "} for cell in row):
                        table_rows.append([clean_markdown(cell) for cell in row])
                    index += 1
                self.table(table_rows)
                continue

            heading_match = re.match(r"^(#{1,3})\s+(.+)$", line)
            if heading_match:
                flush_paragraph()
                level = len(heading_match.group(1))
                text = heading_match.group(2)
                if level == 1:
                    index += 1
                    continue
                self.heading(level, text)
                index += 1
                continue

            bullet_match = re.match(r"^-\s+(.+)$", line)
            if bullet_match:
                flush_paragraph()
                self.bullet(bullet_match.group(1))
                index += 1
                continue

            number_match = re.match(r"^(\d+)\.\s+(.+)$", line)
            if number_match:
                flush_paragraph()
                self.bullet(number_match.group(2), number_match.group(1))
                index += 1
                continue

            if line.lower().startswith("last updated:"):
                index += 1
                continue

            paragraph.append(line)
            index += 1

        flush_paragraph()

    def decorate(self, page: Page, page_number: int, total_pages: int) -> list[str]:
        commands: list[str] = []
        if page.show_header:
            header = Page()
            header.text(MARGIN_X, PAGE_HEIGHT - 38, "Your Kayaking Journal", 9, "F3", COLORS["orange"])
            header.text(PAGE_WIDTH - 154, PAGE_HEIGHT - 38, "User Manual", 9, "F1", COLORS["faint"])
            header.line(MARGIN_X, PAGE_HEIGHT - 52, PAGE_WIDTH - MARGIN_X, PAGE_HEIGHT - 52, COLORS["line"], 0.8)
            commands.extend(header.commands)

        footer = Page()
        footer.line(MARGIN_X, 38, PAGE_WIDTH - MARGIN_X, 38, COLORS["line"], 0.6)
        footer.text(MARGIN_X, 22, "Private beta guide - not a navigation authority", 8.5, "F1", COLORS["faint"])
        footer.text(PAGE_WIDTH - 82, 22, f"{page_number} / {total_pages}", 8.5, "F1", COLORS["faint"])
        commands.extend(page.commands)
        commands.extend(footer.commands)
        return commands

    def write(self, path: Path) -> None:
        objects: dict[int, bytes] = {}
        objects[3] = b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"
        objects[4] = b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>"
        objects[5] = b"<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>"

        next_id = 6
        image_resource = ""
        if self.logo:
            data, width, height = self.logo
            image_id = next_id
            next_id += 1
            objects[image_id] = (
                f"<< /Type /XObject /Subtype /Image /Width {width} /Height {height} "
                f"/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {len(data)} >>\n"
                "stream\n"
            ).encode("latin-1") + data + b"\nendstream"
            image_resource = f"/XObject << /ImLogo {image_id} 0 R >>"

        page_ids: list[int] = []
        total = len(self.pages)
        for index, page in enumerate(self.pages, start=1):
            commands = self.decorate(page, index, total)
            stream = "\n".join(commands).encode("latin-1")
            content_id = next_id
            next_id += 1
            page_id = next_id
            next_id += 1
            objects[content_id] = b"<< /Length " + str(len(stream)).encode("latin-1") + b" >>\nstream\n" + stream + b"\nendstream"
            resources = f"<< /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> {image_resource} >>"
            objects[page_id] = (
                f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {PAGE_WIDTH:.0f} {PAGE_HEIGHT:.0f}] "
                f"/Resources {resources} /Contents {content_id} 0 R >>"
            ).encode("latin-1")
            page_ids.append(page_id)

        kids = " ".join(f"{page_id} 0 R" for page_id in page_ids)
        objects[1] = b"<< /Type /Catalog /Pages 2 0 R >>"
        objects[2] = f"<< /Type /Pages /Kids [{kids}] /Count {len(page_ids)} >>".encode("latin-1")

        output = bytearray(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
        offsets: dict[int, int] = {}
        for object_id in sorted(objects):
            offsets[object_id] = len(output)
            output.extend(f"{object_id} 0 obj\n".encode("latin-1"))
            output.extend(objects[object_id])
            output.extend(b"\nendobj\n")

        xref = len(output)
        max_id = max(objects)
        output.extend(f"xref\n0 {max_id + 1}\n".encode("latin-1"))
        output.extend(b"0000000000 65535 f \n")
        for object_id in range(1, max_id + 1):
            offset = offsets.get(object_id, 0)
            output.extend(f"{offset:010d} 00000 n \n".encode("latin-1"))
        output.extend(
            f"trailer\n<< /Size {max_id + 1} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n".encode("latin-1")
        )
        path.write_bytes(bytes(output))


def manual_content_lines() -> list[str]:
    lines = MANUAL.read_text(encoding="utf-8").splitlines()
    start = 0
    for index, line in enumerate(lines):
        if line.strip() == "## Quick Start":
            start = index
            break
    return lines[start:]


def main() -> None:
    logo = make_logo_jpeg()
    pdf = ManualPdf(logo)
    pdf.cover()
    pdf.at_a_glance()
    pdf.parse_markdown(manual_content_lines())
    pdf.write(OUTPUT)
    print(f"Wrote {OUTPUT.relative_to(ROOT)} ({OUTPUT.stat().st_size:,} bytes)")


if __name__ == "__main__":
    main()
