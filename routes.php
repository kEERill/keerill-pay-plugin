<?php

Route::group(['prefix' => 'api'], function(){
    Route::any('payment/{code}/{slug}', function($code, $accessPoint) {
        return \KEERill\Pay\Classes\PaymentManager::runAccessPoint($code, $accessPoint);
    })->where('slug', '(.*)?');
});