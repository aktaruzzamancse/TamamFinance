define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
        'jquery/ui',
        'Magento_Ui/js/model/messageList',
        'mage/translate'
    ],
    function (
        Component,
        quote,
        $,
        fullScreenLoader,
        placeOrderAction,
        additionalValidators,
        url,
        jqueryUi,
        globalMessageList,
        mage

    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Baytonia_TamamFinance/payment/tamam'
            },
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            validateName: function () {
                var validator = $('#' + this.getCode() + '-form').validate();
                validator.element('#national_id');
            },
            validateNumber: function () {
                var validator = $('#' + this.getCode() + '-form').validate();
                validator.element('#my-date');
            },
            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },
            moyasarPaymentUrl: function () {
                return window.checkoutConfig.moyasar_credit_card.payment_url;
            },
            getOrderId: function () {
                return $.ajax({
                    url: url.build('baytonia_tamamfinance/order/data'),
                    method: 'GET',
                    dataType: 'json'
                });
            },
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (!this.validate() || !additionalValidators.validate()) {
                    return false;
                }

                this.isPlaceOrderActionAllowed(false);
                this.payment = null;

                var $form = $('#' + this.getCode() + '-form');
                var formData = $form.serialize();
                console.log(formData);
                fullScreenLoader.startLoader();
                // console.log("order data ",orderData);
               
                
                // if(requestToken.response.redirection_url){
                //     window.location.href = requestToken.response.redirection_url;
                //     // var intOrder = self.intOrder(requestToken.response.transaction_id);
                // }
                // var requestToken = self.requestToken(formData);
                //         console.log('requestToken ', requestToken);
                //         requestToken = JSON.parse(requestToken.response);
                //         console.log(requestToken.response.redirection_url);
                        
                // return false;
                this.placeMagentoOrder().done(function (
                    
                ) {
                    self.getOrderId().done(function (orderData) {
                        var requestToken = self.requestToken(formData);
                        console.log('requestToken ', requestToken);
                        requestToken = JSON.parse(requestToken.response);
                        console.log(requestToken.response.redirection_url);
              
                        if(requestToken.response.redirection_url){
                            window.location.href = requestToken.response.redirection_url;
                            // var intOrder = self.intOrder(requestToken.response.transaction_id);
                        }else {
                            self.cancelOrder([mage('Error! Could not place order.')]);
                        }
                       
                    })
                        .fail(self.handlePlaceOrderFail)
                })
                    .fail(self.handlePlaceOrderFail);

                return true;
            },
            placeMagentoOrder: function () {
                return $.when(placeOrderAction(this.getData(), this.messageContainer));
            },
            updateOrderPayment: function (payment) {
                return $.ajax({
                    url: url.build('moyasar_mysr/order/update'),
                    method: 'POST',
                    data: payment,
                    dataType: 'json'
                });
            },
            requestToken: function (formData) {
                var test = "national_id=4444&my-date=2023-05-04";
                $.ajax({
                    url: url.build('baytonia_tamamfinance/order/payment'),
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    async: false,
                    success: function (testdata) {
                        console.log(testdata);
                        test = testdata;

                    }
                });
                return test;
            },
           
            handlePlaceOrderFail: function () {
                this.isPlaceOrderActionAllowed(true);
                globalMessageList.addErrorMessage({ message: mage('Could not place order.') });
            },
            cancelOrder(errors) {
                var self = this;
                var paymentId = this.payment ? this.payment.id : null;

                sendCancelOrder(paymentId, errors).always(function (data) {
                    self.isPlaceOrderActionAllowed(true);
                    fullScreenLoader.stopLoader();
                    globalMessageList.addErrorMessage({ message: errors.join(", ") });
                });
            }
        });
    }
);
