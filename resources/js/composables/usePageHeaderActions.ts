import { shallowRef } from 'vue';

export interface PageHeaderAction {
    id: string;
    label: string;
    active?: boolean;
    onClick: () => void;
}

const pageHeaderActions = shallowRef<PageHeaderAction[]>([]);

export function usePageHeaderActions() {
    function setPageHeaderActions(actions: PageHeaderAction[]) {
        pageHeaderActions.value = actions;
    }

    function clearPageHeaderActions() {
        pageHeaderActions.value = [];
    }

    return {
        pageHeaderActions,
        setPageHeaderActions,
        clearPageHeaderActions,
    };
}
