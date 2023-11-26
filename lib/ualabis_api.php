<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "ualabis_response.php";

/**
 * UalaBis api
 */

class UalabisApi
{
    /**
     * @var string The Username
     */
    private $user_name;

    /**
     * @var string The client ID
     */
    private $client_id;

    /**
     * @var string The client secret ID
     */
    private $client_secret_id;

    /**
     * @var string The grant type
     */
    private $grant_type;

    /**
     * Initializes the class.
     *
     * @param string $user_name The username
     * @param string $client_id The client ID
     * @param string $client_secret The client secret id
     * @param string $grant_type The grant type
     */

    public function __construct($user_name, $client_id, $client_secret_id, $grant_type)
    {
        $this->user_name = $user_name;
        $this->client_id = $client_id;
        $this->client_secret_id = $client_secret_id;
        $this->grant_type = $grant_type;
    }

    /**
     * Send a request to UalaBis API.
     *
     * @param string $method Specifies the endpoint and method to invoke
     * @param array $params The parameters to include in the api call
     * @param array $token The parameters to include in the api call
     * @param string $type The HTTP request type
     * @return stdClass An object containing the api response
     */
    private function apiRequest($method, array $params = [], $token, $type = "GET")
    {

        // Send request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " .$token,
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Build GET request
        if ($type == "GET") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            $url = $method . "?" . http_build_query($params);
        }

        // Build POST request
        if ($type == "POST") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POST, true);

            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            }
        }

        // Execute request
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = new stdClass();
        if (curl_errno($ch)) {
            $data->message = curl_error($ch);
        } else {
            $data = json_decode(curl_exec($ch));
        }
        curl_close($ch);

        return new UalabisResponse($data);
    }

    /**
     * Build the payment request.
     *
     * @param array $params An array containing the following arguments:
     *  - email: Customer's email address
     *  - amount: Amount in kobo (1/100 niara)
     *  - reference: Unique transaction reference. Only -, ., = and alphanumeric characters allowed.
     *  - metadata: An object with the cancel_action property which controls to url for an aborted transaction
     * @return stdClass An object containing the api response
     */
    public function buildToken()
    {
        $params = [
            "user_name" =>$this->user_name,
            "client_id" => $this->client_id,
            "client_secret_id" => $this->client_secret_id,
            "grant_type" => $this->grant_type
        ];

        return $this->apiRequest("https://auth.stage.ua.la/1/auth/token", $params, [], "POST");
    }

    /**
     * Validate this payment.
     *
     * @param string $reference The unique reference code for this payment
     * @return stdClass An object containing the api response
     */
    public function buildPayment($params, $token)
    {
        return $this->apiRequest("https://checkout.stage.ua.la/1/checkout", $params, $token, "POST");
    }

    /**
     * Validate this payment.
     *
     * @param  string $reference The unique reference code for this payment
     * @param string $token The token of the invoice
     * @return stdClass An object containing the api response
     */
    public function checkPayment($reference, $token)
    {
        return $this->apiRequest("https://checkout.stage.ua.la/1/order/" . $reference, [], $token, "GET");

    }
}
