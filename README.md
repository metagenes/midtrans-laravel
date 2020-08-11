# REST API Documentation

This is REST API using Laravel and Midtrans for PHP. 

## Clone 

    git clone https://github.com/metagenes/midtrans-laravel


## Install

    composer install

## Setup .ENV

    make .env file based on .env.example and fill database credential

## Migrate App

    php artisan migrate

## Run app

    php artisan serve


# REST API

The REST API to the app is described below.

## Get list of Things

### Request

## Create a new Transaction

### Request

`POST /`

    curl -i -H 'Accept: application/json' -d 'customer_name=Name&customer_email=Email&customer_phone=123&product_description=description&total_price=12000' http://localhost/midtrans_ci/index.php/product

### Response

   {
    "snap_token": "85aa9207-c06f-407e-8077-16b07a8d415d",
    "redirect_url": "https://app.sandbox.midtrans.com/snap/v1/transactions/85aa9207-c06f-407e-8077-16b07a8d415d",
    "transaction": {
        "transaction_details": {
            "order_id": 3,
            "total price": 12000
        },
        "customer_details": {
            "first_name": "Name",
            "email": "Email",
            "phone": "123"
        },
        "item_details": [
            {
                "id": 3,
                "price": 12000,
                "quantity": 1,
                "name": "Description"
            }
        ]
    }
}

