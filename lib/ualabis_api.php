<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "ualabis_response.php";

/**
 * UaláBis API
 */
class UalabisApi
{
    /**
     * @var string The username
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
     * @param string $client_secret_id The client secret ID
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
     * Send a request to UaláBis API.
     *
     * @param string $method Specifies the endpoint and method to invoke
     * @param array $params The parameters to include in the API call
     * @param array $token The parameters to include in the API call
     * @param string $type The HTTP request type
     *
     * @return stdClass An object containing the API response
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
            "Authorization: Bearer " . $token,
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Build GET request
        if ($type == "GET") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            $method = $method . "?" . http_build_query($params);
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
        curl_setopt($ch, CURLOPT_URL, $method);
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
     * Build the token.
     *
     * @param  array $params An array containing the following arguments:
     *  - user_name: Username for authentication.
     *  - client_id: Application identifier.
     *  - client_secret_id: Application secret for authentication.
     *  - grant_type: Type of operation to obtain a token.
     * @return stdClass API response object.
     */
    public function buildToken()
    {
        $params = [
            "user_name" => $this->user_name,
            "client_id" => $this->client_id,
            "client_secret_id" => $this->client_secret_id,
            "grant_type" => $this->grant_type,
        ];

        return $this->apiRequest("https://auth.prod.ua.la/1/auth/token", $params, [], "POST");
    }

    /**
     * Build the payment request.
     *
     * @param array $params An array with payment request data
     * @param mixed $token The token
     * @return stdClass An object containing the API response
     */
    public function buildPayment($params, $token)
    {
        return $this->apiRequest("https://checkout.prod.ua.la/1/checkout", $params, $token, "POST");
    }

    /**
     * Validate this payment.
     *
     * @param string $reference The unique reference code for this payment
     * @param mixed  $token The token of the invoice
     * @return stdClass An object containing the API response
     */
    public function checkPayment($reference, $token)
    {
        return $this->apiRequest("https://checkout.prod.ua.la/1/order/" . $reference, [], $token, "GET");
    }
}
