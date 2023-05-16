define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'tamam',
                component: 'Baytonia_TamamFinance/js/view/payment/method-renderer/tamam-method'
            }
        );
        return Component.extend({});
    }
);