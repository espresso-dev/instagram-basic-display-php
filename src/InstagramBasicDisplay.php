<?php

namespace EspressoDev\InstagramBasicDisplay;

/**
 * Class InstagramBasicDisplay
 * @package EspressoDev\InstagramBasicDisplay
 */
class InstagramBasicDisplay
{
    const API_URL = 'https://graph.instagram.com/';

    const API_OAUTH_URL = 'https://api.instagram.com/oauth/authorize';

    const API_OAUTH_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

    const API_TOKEN_EXCHANGE_URL = 'https://graph.instagram.com/access_token';

    const API_TOKEN_REFRESH_URL = 'https://graph.instagram.com/refresh_access_token';

    /**
     * @var string
     */
    private $_appId;

    /**
     * @var string
     */
    private $_appSecret;

    /**
     * @var string
     */
    private $_redirectUri;

    /**
     * @var string
     */
    private $_accesstoken;

    /**
     * @var string[]
     */
    private $_scopes = ['user_profile', 'user_media'];

    /**
     * @var string
     */
    private $_userFields = 'account_type, id, media_count, username';

    /**
     * @var string
     */
    private $_mediaFields = 'caption, id, media_type, media_url, permalink, thumbnail_url, timestamp, username, children{id, media_type, media_url, permalink, thumbnail_url, timestamp, username}';

    /**
     * @var string
     */
    private $_mediaChildrenFields = 'id, media_type, media_url, permalink, thumbnail_url, timestamp, username';

    /**
     * @var int
     */
    private $_timeout = 90000;

    /**
     * @var int
     */
    private $_connectTimeout = 20000;

    /**
     * InstagramBasicDisplay constructor.
     * @param string[string]|string $config configuration parameters
     * @throws InstagramBasicDisplayException
     */
    public function __construct($config = null)
    {
        if (is_array($config)) {
            $this->setAppId($config['appId']);
            $this->setAppSecret($config['appSecret']);
            $this->setRedirectUri($config['redirectUri']);

            if (isset($config['timeout'])) {
                $this->setTimeout($config['timeout']);
            }

            if (isset($config['connectTimeout'])) {
                $this->setConnectTimeout($config['connectTimeout']);
            }
        } elseif (is_string($config)) {
            // For read-only
            $this->setAccessToken($config);
        } else {
            throw new InstagramBasicDisplayException('Error: __construct() - Configuration data is missing.');
        }
    }

    /**
     * @param string[] $scopes
     * @param string $state
     * @return string
     * @throws InstagramBasicDisplayException
     */
    public function getLoginUrl($scopes = ['user_profile', 'user_media'], $state = '')
    {
        if (is_array($scopes) && count(array_intersect($scopes, $this->_scopes)) === count($scopes)) {
            return self::API_OAUTH_URL . '?client_id=' . $this->getAppId() . '&redirect_uri=' . urlencode($this->getRedirectUri()) . '&scope=' . implode(',',
                $scopes) . '&response_type=code' . ($state != '' ? '&state=' . $state : '');
        }

        throw new InstagramBasicDisplayException("Error: getLoginUrl() - The parameter isn't an array or invalid scope permissions used.");
    }

    /**
     * @param int $id
     * @return object
     * @throws InstagramBasicDisplayException
     */
    public function getUserProfile($id = 0)
    {
        if ($id === 0) {
            $id = 'me';
        }

        return $this->_makeCall($id, ['fields' => $this->_userFields]);
    }

    /**
     * @param string $id
     * @param int $limit
     * @param string|null $before
     * @param string|null $after
     * @return object
     * @throws InstagramBasicDisplayException
     */
    public function getUserMedia($id = 'me', $limit = 0, $before = null, $after = null)
    {
        $params = [
            'fields' => $this->_mediaFields
        ];

        if ($limit > 0) {
            $params['limit'] = $limit;
        }
        if (isset($before)) {
            $params['before'] = $before;
        }
        if (isset($after)) {
            $params['after'] = $after;
        }

        return $this->_makeCall($id . '/media', $params);
    }

    /**
     * @param string $id
     * @return object
     * @throws InstagramBasicDisplayException
     */
    public function getMedia($id)
    {
        return $this->_makeCall($id, ['fields' => $this->_mediaFields]);
    }

    /**
     * @param string $id
     * @return object
     * @throws InstagramBasicDisplayException
     */
    public function getMediaChildren($id)
    {
        return $this->_makeCall($id . '/children', ['fields' => $this->_mediaChildrenFields]);
    }

    /**
     * @param object $obj
     * @return object|null
     * @throws InstagramBasicDisplayException
     */
    public function pagination($obj)
    {
        if (is_object($obj) && !is_null($obj->paging)) {
            if (!isset($obj->paging->next)) {
                return null;
            }

            $apiCall = explode('?', $obj->paging->next);

            if (count($apiCall) < 2) {
                return null;
            }

            $function = str_replace(self::API_URL, '', $apiCall[0]);
            parse_str($apiCall[1], $params);

            // No need to include access token as this will be handled by _makeCall
            unset($params['access_token']);

            return $this->_makeCall($function, $params);
        }

        throw new InstagramBasicDisplayException("Error: pagination() | This method doesn't support pagination.");
    }

    /**
     * @param string $code
     * @param bool $tokenOnly
     * @return object|string
     * @throws InstagramBasicDisplayException
     */
    public function getOAuthToken($code, $tokenOnly = false)
    {
        $apiData = array(
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getAppSecret(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getRedirectUri(),
            'code' => $code
        );

        $result = $this->_makeOAuthCall(self::API_OAUTH_TOKEN_URL, $apiData);

        return !$tokenOnly ? $result : $result->access_token;
    }

    /**
     * @param string $token
     * @param bool $tokenOnly
     * @return object|string
     * @throws InstagramBasicDisplayException
     */
    public function getLongLivedToken($token, $tokenOnly = false)
    {
        $apiData = array(
            'client_secret' => $this->getAppSecret(),
            'grant_type' => 'ig_exchange_token',
            'access_token' => $token
        );

        $result = $this->_makeOAuthCall(self::API_TOKEN_EXCHANGE_URL, $apiData, 'GET');

        return !$tokenOnly ? $result : $result->access_token;
    }

    /**
     * @param string $token
     * @param bool $tokenOnly
     * @return object|string
     * @throws InstagramBasicDisplayException
     */
    public function refreshToken($token, $tokenOnly = false)
    {
        $apiData = array(
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token
        );

        $result = $this->_makeOAuthCall(self::API_TOKEN_REFRESH_URL, $apiData, 'GET');

        return !$tokenOnly ? $result : $result->access_token;
    }

    /**
     * @param string $function
     * @param string[]|null $params
     * @param string $method
     * @return object
     * @throws InstagramBasicDisplayException
     */
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

    /**
     * @param string $apiHost
     * @param string[] $params
     * @param string $method
     * @return object
     * @throws InstagramBasicDisplayException
     */
    private function _makeOAuthCall($apiHost, $params, $method = 'POST')
    {
        $paramString = null;

        if (isset($params) && is_array($params)) {
            $paramString = '?' . http_build_query($params);
        }

        $apiCall = $apiHost . (('GET' === $method) ? $paramString : null);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiCall);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->_timeout);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, count($params));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $jsonData = curl_exec($ch);

        if (!$jsonData) {
            throw new InstagramBasicDisplayException('Error: _makeOAuthCall() - cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($jsonData);
    }

    /**
     * @param string $token
     */
    public function setAccessToken($token)
    {
        $this->_accesstoken = $token;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->_accesstoken;
    }

    /**
     * @param string $appId
     */
    public function setAppId($appId)
    {
        $this->_appId = $appId;
    }

    /**
     * @return string
     */
    public function getAppId()
    {
        return $this->_appId;
    }

    /**
     * @param string $appSecret
     */
    public function setAppSecret($appSecret)
    {
        $this->_appSecret = $appSecret;
    }

    /**
     * @return string
     */
    public function getAppSecret()
    {
        return $this->_appSecret;
    }

    /**
     * @param string $redirectUri
     */
    public function setRedirectUri($redirectUri)
    {
        $this->_redirectUri = $redirectUri;
    }

    /**
     * @return string
     */
    public function getRedirectUri()
    {
        return $this->_redirectUri;
    }

    /**
     * @param string $fields
     */
    public function setUserFields($fields)
    {
        $this->_userFields = $fields;
    }

    /**
     * @param string $fields
     */
    public function setMediaFields($fields)
    {
        $this->_mediaFields = $fields;
    }

    /**
     * @param string $fields
     */
    public function setMediaChildrenFields($fields)
    {
        $this->_mediaChildrenFields = $fields;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->_timeout = $timeout;
    }

    /**
     * @param int $connectTimeout
     */
    public function setConnectTimeout($connectTimeout)
    {
        $this->_connectTimeout = $connectTimeout;
    }
}
