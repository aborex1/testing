'use strict';

import ProductReviewsComponent from './components/ProductReviewsComponent.vue';
import TopProductsGroupComponent from './components/TopProductsGroupComponent.vue';

window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const { createApp } = Vue

const app = createApp({})

app.component('product-reviews-component', ProductReviewsComponent);
app.component('top-products-group-component', TopProductsGroupComponent)

app.config.globalProperties.__ = (key) => {
    return window.trans[key] !== 'undefined' ? window.trans[key] : key;
};

app.mount('#main-section')
