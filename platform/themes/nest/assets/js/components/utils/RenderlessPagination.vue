<template>
    <slot v-bind="{ data, currentPage, lastPage, hasMorePages, hasPages, pageButtonEvents }"></slot>
</template>

<script>
export default {
    props: {
        data: {
            type: Object,
            default: () => {}
        },
    },
    computed: {
        currentPage () {
            return this.data.current_page;
        },
        lastPage () {
            return this.data.last_page;
        },
        hasMorePages() {
            return this.currentPage && this.currentPage < this.lastPage;
        },
        hasPages() {
            return this.currentPage && (this.currentPage !== 1 || this.hasMorePages);
        }
    },
    methods: {
        selectPage (params) {
            this.$emit('on-click-paging', params);
        },
        pageButtonEvents: (params) => ({
            click: (e) => {
                e.preventDefault();
                this.selectPage(params);
            }
        })
    },
}
</script>
