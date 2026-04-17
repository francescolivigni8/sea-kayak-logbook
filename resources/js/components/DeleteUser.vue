<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <section class="journal-panel px-5 py-5 md:px-6">
        <div class="space-y-6">
            <Heading
                variant="small"
                title="Delete account"
                description="Keep this as a final action only. If you simply want to reorganise your journal, do not use this."
            />

            <div class="journal-banner journal-banner--danger">
                Deleting the account removes the profile, sessions, notes, and
                uploaded media permanently.
            </div>

            <Dialog>
                <DialogTrigger as-child>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-full border border-[rgba(255,138,128,0.42)] bg-[rgba(255,255,255,0.9)] px-4 py-3 text-sm font-semibold text-[#9c4841] transition hover:-translate-y-px hover:bg-[rgba(255,241,240,0.95)]"
                        data-test="delete-user-button"
                    >
                        Delete account
                    </button>
                </DialogTrigger>
                <DialogContent
                    class="border-[color:var(--journal-line)] bg-white/95 text-[color:var(--journal-text)] shadow-[var(--journal-shadow)] sm:rounded-[28px]"
                >
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Delete this account?</DialogTitle>
                            <DialogDescription>
                                Once removed, the account, sessions, notes, and
                                media attached to it are gone. Enter your
                                password only if you want to confirm the
                                permanent delete.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only"
                                >Password</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                ref="passwordInput"
                                class="journal-input"
                                placeholder="Password"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    Cancel
                                </Button>
                            </DialogClose>

                            <button
                                type="submit"
                                :disabled="processing"
                                class="inline-flex items-center justify-center rounded-full bg-[#cf6157] px-4 py-2.5 text-sm font-semibold text-white transition hover:-translate-y-px hover:bg-[#c4534a] disabled:cursor-not-allowed disabled:opacity-60"
                                data-test="confirm-delete-user-button"
                            >
                                {{
                                    processing
                                        ? 'Deleting...'
                                        : 'Delete account'
                                }}
                            </button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </section>
</template>
