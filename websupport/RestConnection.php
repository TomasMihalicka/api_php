<?php

namespace websupport;
use Exception;

/**
 * Class RestConnection
 * @package websupport
 */
class RestConnection {

    /**
     * Using SSL
     *
     * @var bool
     */
    protected $ssl = false;

    /**
     * @var
     */
    protected $caFile;

    /**
     * Api URL
     *
     * @var string
     */
    protected $url;

    /**
     * Public Key / Username
     *
     * @var string
     */
    protected $publicKey;

    /**
     * Private Key / Password
     *
     * @var string
     */
    protected $privateKey;

    /**
     * Persistent Connection
     *
     * @var bool
     */
    protected $persistent = true;

    /**
     * Connection Object
     *
     * @var object
     */
    protected $connection;

    /**
     * Additional HTTP Headers
     *
     * @var array
     */
    protected $aditionalHeaders = array();

    /**
     * Class Constructor
     *
     * @param $url
     * @param null $publicKey
     * @param null $privateKey
     */
    public function __construct($url, $publicKey = null, $privateKey = null) {
        $this->url = $url;
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        if (strpos($url, 'https') === 0) {
            $this->ssl = true;
        }
    }

    /**
     * REST GET Method
     *
     * @param $path
     * @param null $params
     * @throws RestAccessDeniedException
     * @throws RestAuthenticationException
     * @throws RestException
     * @throws RestJsonParseException
     * @throws RestNotFoundException
     * @throws RestNotImplementedException
     * @throws RestServerErrorException
     *
     * @return object
     */
    public function get($path, $params = null) {
        list ($body, $responseCode) = $this->doRequest('GET', $path, $params !== null ? json_encode($params) : null);
        if ($responseCode === 200) {
            return $this->parseJson($body);
        } else {
            $this->throwExceptionByStatus($responseCode, $this->parseJson($body));
        }
    }

    /**
     * REST POST Method
     *
     * @param $path
     * @param null $params
     * @throws RestAccessDeniedException
     * @throws RestAuthenticationException
     * @throws RestException
     * @throws RestJsonParseException
     * @throws RestNotFoundException
     * @throws RestNotImplementedException
     * @throws RestServerErrorException
     *
     * @return object
     */
    public function post($path, $params = null) {
        list ($body, $responseCode) = $this->doRequest('POST', $path, json_encode($params));
        if ($responseCode === 200 || $responseCode === 201 || $responseCode === 422 || $responseCode === 400) {
            return $this->parseJson($body);
        } else {
            $this->throwExceptionByStatus($responseCode, $this->parseJson($body));
        }
    }

    /**
     * REST PUT Method
     *
     * @param $path
     * @param null $params
     * @throws RestAccessDeniedException
     * @throws RestAuthenticationException
     * @throws RestException
     * @throws RestJsonParseException
     * @throws RestNotFoundException
     * @throws RestNotImplementedException
     * @throws RestServerErrorException
     *
     * @return object
     */
    public function put($path, $params = null) {
        list ($body, $responseCode) = $this->doRequest('PUT', $path, json_encode($params));
        if ($responseCode === 200 || $responseCode === 201 || $responseCode === 422 || $responseCode === 400) {
            return $this->parseJson($body);
        } else {
            $this->throwExceptionByStatus($responseCode, $this->parseJson($body));
        }
    }

    /**
     * REST DELETE Method
     *
     * @param $path
     * @param null $params
     * @throws RestAccessDeniedException
     * @throws RestAuthenticationException
     * @throws RestException
     * @throws RestJsonParseException
     * @throws RestNotFoundException
     * @throws RestNotImplementedException
     * @throws RestServerErrorException
     *
     * @return object
     */
    public function delete($path, $params = null) {
        if ($params === null) {
            list ($body, $responseCode) = $this->doRequest('DELETE', $path);
        } else {
            list ($body, $responseCode) = $this->doRequest('DELETE', $path, json_encode($params));
        }
        if ($responseCode === 200) {
            return $this->parseJson($body);
        } else {
            $this->throwExceptionByStatus($responseCode, $this->parseJson($body));
        }
    }

    /**
     * Do Request to API
     *
     * @param $httpVerb
     * @param $path
     * @param null $requestBody
     * @return array
     * @throws RestException
     */
    protected function doRequest($httpVerb, $path, $requestBody = null) {
        if (strpos($path, $this->url) === 0) {
            $path = substr($path, strlen($this->url) - 1);
        }

        $curl = $this->getConnection();
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $httpVerb);
        curl_setopt($curl, CURLOPT_URL, $this->url . $path);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge(array(
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Websupport PHP Library',
        ), $this->aditionalHeaders));

        if ($this->publicKey !== null && $this->privateKey !== null) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, $this->publicKey . ':' . $this->privateKey);
        }

        if ($this->ssl) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            if ($this->caFile) {
                curl_setopt($curl, CURLOPT_CAINFO, $this->caFile);
            }
        } else {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }

        if (!empty($requestBody)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpStatus == 0) {
            $errorMessage = curl_error($curl);
            throw new RestException($errorMessage);
        }

        if (!$this->persistent) {
            curl_close($curl);
        }

        return array($response, $httpStatus);
    }

    /**
     * Get CURL Connection object
     *
     * @return object|resource
     */
    protected function getConnection() {
        if ($this->persistent) {
            if ($this->connection === null) {
                $this->connection = $curl = curl_init();
            } else {
                $curl = $this->connection;
            }
        } else {
            $curl = curl_init();
        }
        return $curl;
    }

    /**
     * Throws Exception by API Status Code
     *
     * @param $status
     * @param $body
     * @param null $msg
     * @throws RestAccessDeniedException
     * @throws RestAuthenticationException
     * @throws RestException
     * @throws RestNotFoundException
     * @throws RestNotImplementedException
     * @throws RestServerErrorException
     */
    protected function throwExceptionByStatus($status, $body, $msg = null) {
        if (isset($body->message) && is_string($body->message) && $msg === null) {
            $msg = $body->message;
        }
        if ($status == 401) {
            throw new RestAuthenticationException($msg, $status, $body);
        } elseif ($status == 403) {
            throw new RestAccessDeniedException($msg, $status, $body);
        } elseif ($status == 404) {
            throw new RestNotFoundException($msg, $status, $body);
        } elseif ($status == 500) {
            throw new RestServerErrorException($msg, $status, $body);
        } elseif ($status == 501) {
            throw new RestNotImplementedException($msg, $status, $body);
        } else {
            throw new RestException($msg, $status, $body);
        }
    }

    /**
     * Parse JSON to Object
     *
     * @param $json
     * @return object
     * @throws RestJsonParseException
     */
    protected function parseJson($json) {
        $parsed = json_decode($json);
        if ($parsed === null) {
            throw new RestJsonParseException('Error while parsing json: ' . substr($json, 0, 250),200,$json);
        }
        return $parsed;
    }

    /**
     * Set Cat Certificate
     *
     * @param $f
     */
    public function setCaFile($f) {
        $this->caFile = $f;
        $this->ssl = true;
    }

    /**
     * Set Persistent Connection
     *
     * @param $v
     */
    public function setPersistentConnection($v) {
        $this->persistent = (bool) $v;
    }

    /**
     * Set Addition HTTP Headers
     *
     * @param $h
     * @throws \Exception
     */
    public function setAditionalHeaders($h) {
        if (!is_array($h)) {
            throw new Exception('Wrong headers format');
        }
        $this->aditionalHeaders = $h;
    }
}

/**
 * Class RestException
 * @package websupport
 */
class RestException extends Exception {

    /**
     * Exception Error Code
     *
     * @var null
     */
    public $errorCode;

    /**
     * Exception Body
     *
     * @var null
     */
    public $body;

    /**
     * Class Constructor
     *
     * @param string $message
     * @param null $errorCode
     * @param null $body
     */
    public function __construct($message, $errorCode = null, $body = null) {
        $this->errorCode = $errorCode;
        $this->body = $body;
        parent::__construct($message);
    }

}

/**
 * Class RestAccessDeniedException
 * @package websupport
 */
class RestAccessDeniedException extends RestException {}

/**
 * Class RestAuthenticationException
 * @package websupport
 */
class RestAuthenticationException extends RestException {}

/**
 * Class RestServerErrorException
 * @package websupport
 */
class RestServerErrorException extends RestException {}

/**
 * Class RestNotImplementedException
 * @package websupport
 */
class RestNotImplementedException extends RestException {}

/**
 * Class RestNotFoundException
 * @package websupport
 */
class RestNotFoundException extends RestException {}

/**
 * Class RestJsonParseException
 * @package websupport
 */
class RestJsonParseException extends RestException {}
