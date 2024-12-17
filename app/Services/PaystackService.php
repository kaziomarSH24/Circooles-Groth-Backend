<?php
namespace App\Services;

class PaystackService
{
    protected $apiUrl;
    protected $apiKey;
    protected $email;
    protected $publicKey;

    public function __construct()
    {
        $this->apiUrl = env('PAYSTACK_PAYMENT_URL');
        $this->apiKey = env('PAYSTACK_SECRET_KEY');
        $this->email = env('MERCHANT_EMAIL');
        $this->publicKey = env('PAYSTACK_PUBLIC_KEY');
    }

    //generic method to send request to paystack api for all endpoints
    protected function sendRequest($method, $endpoint, $data = null)
    {
        // dd("{$this->apiUrl}{$endpoint}");
        $curl = curl_init();
        $options = [
            CURLOPT_URL => "{$this->apiUrl}{$endpoint}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                "Content-Type: application/json",
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response);
    }


    //initialize transaction
    public function initializeTransaction($data)
    {
        return $this->sendRequest('POST', '/transaction/initialize', $data);
    }

    //verify transaction
    public function verifyTransaction($reference)
    {
        return $this->sendRequest('GET', "/transaction/verify/$reference");
    }

    //get payment data
    public function getPaymentData($reference)
    {
        return $this->sendRequest('GET', "/paymentrequest/$reference");
    }

    //get customer
    public function getCustomer($code)
    {
        return $this->sendRequest('GET', "/customer/$code");
    }

    //update customer
    public function updateCustomer($code, $data)
    {
        return $this->sendRequest('PUT', "/customer/$code", $data);
    }

    //validate customer
    public function validateCustomer($code, $data)
    {
        return $this->sendRequest('POST', "/customer/$code/identification", $data);
    }


    /* Subaccount */
    //subaccount create
    public function createSubaccount($data)
    {
        return $this->sendRequest('POST', '/subaccount', $data);
    }

    //subaccount list
    public function listSubaccount()
    {
        return $this->sendRequest('GET', '/subaccount');
    }

    //subaccount fetch
    public function fetchSubaccount($code)
    {
        return $this->sendRequest('GET', "/subaccount/$code");
    }

    //update subaccount
    public function updateSubaccount($code, $data)
    {
        return $this->sendRequest('PUT', "/subaccount/$code", $data);
    }

    //payment split
    public function paymentSplit($data)
    {
        return $this->sendRequest('POST', '/split', $data);
    }

/**
 * Transfer Recipients
 */
    //Transfers Recipients
    public function createRecipient($data)
    {
        return $this->sendRequest('POST', '/transferrecipient', $data);
    }

    //List Transfer Recipients
    public function listRecipient()
    {
        return $this->sendRequest('GET', '/transferrecipient');
    }

    //fetch transfer recipient
    public function fetchRecipient($code)
    {
        return $this->sendRequest('GET', "/transferrecipient/$code");
    }

    //update transfer recipient
    public function updateRecipient($code, $data)
    {
        return $this->sendRequest('PUT', "/transferrecipient/$code", $data);
    }

    //delete transfer recipient
    public function deleteRecipient($code)
    {
        return $this->sendRequest('DELETE', "/transferrecipient/$code");
    }

    //transfer recipient
    public function transferRecipient($data)
    {
        return $this->sendRequest('POST', '/transferrecipient', $data);
    }

    /* Transfer refund */
    //refund
    public function refund($data)
    {
        // dd($data);
        return $this->sendRequest('POST', '/refund', $data);
    }

}
