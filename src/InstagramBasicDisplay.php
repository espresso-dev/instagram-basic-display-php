<?php

namespace EspressoDev\InstagramBasicDisplay;

class InstagramBasicDisplay
{
    const API_URL = 'https://graph.instagram.com/';

    private $_accesstoken;

    private $_mediaFields = 'caption, id, media_type, media_url, permalink, thumbnail_url, timestamp, username';
    
    private $_userFields = 'account_type, id, media_count, username';

    private $_timeout = 90000;

    private $_connectTimeout = 20000;

    public function __construct() 
    {

    }

    public function getUserProfile($id = 0)
    {
        if ($id === 0) {
            $id = 'me';
        }

        return $this->_makeCall($id, [ 'fields' => $this->_userFields ]);
    }

    public function getUserMedia($id = 'me', $limit = 0)
    {
        $params = [
            'fields' => $this->_mediaFields
        ];

        if ($limit > 0) {
            $params['limit'] = $limit;
        }

        return $this->_makeCall($id . '/media', $params);
    }

    public function pagination($obj)
    {
        if (is_object($obj) && !is_null($obj->paging)) {
            if (!isset($obj->paging->next)) {
                return;
            }

            $apiCall = explode('?', $obj->paging->next);

            if (count($apiCall) < 2) {
                return;
            }

            $function = str_replace(self::API_URL, '', $apiCall[0]);
            parse_str($apiCall[1], $params);

            // No need to include access token as this will be handled by _makeCall
            unset($params['access_token']);

            return $this->_makeCall($function, $params);
        }
        
        throw new InstagramException("Error: pagination() | This method doesn't support pagination.");
    }

    public function setAccessToken($token)
    {
        $this->_accesstoken = $token;
    }

    public function getAccessToken()
    {
        return $this->_accesstoken;
    }

    protected function _makeCall($function, $params = null, $method = 'GET')
    {
        if (!isset($this->_accesstoken)) {
            throw new InstagramBasicDisplayException("Error: _makeCall() | $function - This method requires an authenticated users access token.");
        }

        $authMethod = '?access_token=' . $this->getAccessToken();

        $paramString = null;

        if (isset($params) && is_array($params)) {
            $paramString = '&' . http_build_query($params);
        }

        $apiCall = self::API_URL . $function . $authMethod . (('GET' === $method) ? $paramString : null);

        $headerData = array('Accept: application/json');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiCall);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerData);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->_connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->_timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $jsonData = curl_exec($ch);

        if (!$jsonData) {
            throw new InstagramBasicDisplayException('Error: _makeCall() - cURL error: ' . curl_error($ch), curl_errno($ch));
        }

        list($headerContent, $jsonData) = explode("\r\n\r\n", $jsonData, 2);

        curl_close($ch);

        return json_decode($jsonData);
    }
}