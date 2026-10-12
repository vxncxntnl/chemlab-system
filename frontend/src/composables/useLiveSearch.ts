import { watch, type Ref } from 'vue';

/**
 * Search as you type: runs the search shortly after the user stops typing —
 * including when the box is cleared, which brings the full list back.
 * (Upper/lower case doesn't matter; the server ignores it.)
 */
export function useLiveSearch(search: Ref<string>, run: () => void, delay = 400) {
    let timer: ReturnType<typeof setTimeout> | null = null;
    watch(search, () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(run, delay);
    });
}
