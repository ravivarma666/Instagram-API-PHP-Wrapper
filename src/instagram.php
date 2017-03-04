<?php

//namespace RaviVarma666\Instagram;

/**
 * Instagram API class
 *
 * API Documentation: http://instagram.com/developer/
 */
class Instagram {

  /**
   * The API base URL
   */
  const API_URL = 'https://api.instagram.com/v1/';

  /**
   * The API OAuth URL
   */
  const API_OAUTH_URL = 'https://api.instagram.com/oauth/authorize';

  /**
   * The OAuth token URL
   */
  const API_OAUTH_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

  /**
   * HTTP GET Method
   */
  const HTTP_METHOD_GET = 'GET';
  /**
   * HTTP POST Method
   */
  const HTTP_METHOD_POST = 'POST';
  /**
   * HTTP POST Method
   */
  const HTTP_METHOD_PUT = 'PUT';
  /**
   * HTTP DELETE Method
   */
  const HTTP_METHOD_DELETE = 'DELETE';
  
  /**
   * The Instagram API Key
   *
   * @var string
   */
  private $_apikey;

  /**
   * The Instagram OAuth API secret
   *
   * @var string
   */
  private $_apisecret;

  /**
   * The callback URL
   *
   * @var string
   */
  private $_callbackurl;

  /**
   * The user access token
   *
   * @var string
   */
  private $_accesstoken;

  /**
   * Whether a signed header should be used
   *
   * @var boolean
   */
  private $_signedheader = false;

  /**
   * Available scopes
   *
   * @var array
   */
  private $_scopes = array('basic', 'likes', 'comments', 'relationships', 'public_content', 'follower_list');

  /**
   * Available actions
   *
   * @var array
   */
  private $_actions = array('follow', 'unfollow', 'block', 'unblock', 'approve', 'deny');

  /**
   * Default constructor
   *
   * @param array|string $config          Instagram configuration data
   * @return void
   */
  public function __construct($config) {
    if (true === is_array($config)) {
      // if you want to access user data
      $this->setApiKey($config['apiKey']);
      $this->setApiSecret($config['apiSecret']);
      $this->setApiCallback($config['apiCallback']);
    } else {
      throw new \Exception("Error: __construct() - Configuration data is missing.");
    }
  }

  /**
   * Generates the OAuth login URL
   *
   * @param array [optional] $scope       Requesting additional permissions
   * @return string                       Instagram OAuth login URL
   */
  public function getLoginUrl($scope = array('basic')) {
    if (is_array($scope) && count(array_intersect($scope, $this->_scopes)) === count($scope)) {
      return self::API_OAUTH_URL . '?client_id=' . $this->getApiKey() . '&redirect_uri=' . urlencode($this->getApiCallback()) . '&scope=' . implode('+', $scope) . '&response_type=code';
    } else {
      throw new \Exception("Error: getLoginUrl() - The parameter isn't an array or invalid scope permissions used.");
    }
  }

  /**
   * Search for a user
   *
   * @param string $name                  Instagram username
   * @param integer [optional] $limit     Limit of returned results
   * @return mixed
   */
  public function searchUser($name, $limit = null) {
    $params = array('q' => $name);
    if(!empty($limit) && is_numeric($limit))
      $params['count'] = $limit;
    return $this->_makeRequest('users/search', $params);
  }

  /**
   * Get user info
   *
   * @param integer [optional] $id        Instagram user ID
   * @return mixed
   */
  public function getUser($id = 'self') {
    return $this->_makeRequest('users/' . $id);
  }


  /**
   * Get user recent media
   *
   * @param integer [optional] $id        Instagram user ID
   * @param integer [optional] $limit     Limit of returned results
   * @return mixed
   */
  public function getUserMedia($id = 'self', $limit = null, $max_id = null, $min_id = null) {
    $params = array();
    if(!empty($limit) && is_numeric($limit))
      $params['count'] = $limit;
    if(!empty($max_id))
      $params['max_id'] = $max_id;
    if(!empty($min_id))
      $params['min_id'] = $min_id;
    return $this->_makeRequest('users/' . $id . '/media/recent', $params);
  }

  /**
   * Get the liked photos of a user
   *
   * @param integer [optional] $limit     Limit of returned results
   * @return mixed
   */
  public function getUserLikes($limit = null, $max_id = null) {
    $params = array();
    if(!empty($limit) && is_numeric($limit))
      $params['count'] = $limit;
    if(!empty($max_id))
      $params['max_id'] = $max_id;
    return $this->_makeRequest('users/self/media/liked', $params);
  }

  /**
   * Get the list of users this user follows
   *
   * @param integer [optional] $id        Instagram user ID
   * @param integer [optional] $limit     Limit of returned results
   * @return mixed
   */
  public function getUserFollows($limit = 0) {
    return $this->_makeRequest('users/self/follows', array('count' => $limit));
  }

  /**
   * Get the list of users this user is followed by
   *
   * @param integer [optional] $limit     Limit of returned results
   * @return mixed
   */
  public function getUserFollowers($limit = 0) {
    return $this->_makeRequest('users/self/followed-by', array('count' => $limit));
  }


  /**
   * Get the list the users who have requested this user's permission to follow.
   *
   * @param integer [optional] $limit     Limit of returned results
   * @return mixed
   */
  public function getUserFollowerRequests($limit = 0) {
    return $this->_makeRequest('users/self/requested-by', array('count' => $limit));
  }

  /**
   * Get information about a relationship to another user
   *
   * @param integer $id                   Instagram user ID
   * @return mixed
   */
  public function getUserRelationship($id) {
    return $this->_makeRequest('users/' . $id . '/relationship');
  }

  /**
   * Modify the relationship between the current user and the target user
   *
   * @param string $action                Action command (follow/unfollow/block/unblock/approve/deny)
   * @param integer $user                 Target user ID
   * @return mixed
   */
  public function modifyRelationship($action, $user) {
    if (true === in_array($action, $this->_actions) && isset($user)) {
      return $this->_makeRequest('users/' . $user . '/relationship', array('action' => $action), self::HTTP_METHOD_POST);
    }
    throw new \Exception("Error: modifyRelationship() | This method requires an action command and the target user id.");
  }

  /**
   * Search media by its location
   *
   * @param float $lat                    Latitude of the center search coordinate
   * @param float $lng                    Longitude of the center search coordinate
   * @param integer [optional] $distance  Distance in metres (default is 1km (distance=1000), max. is 5km)
   * @param long [optional] $minTimestamp Media taken later than this timestamp (default: 5 days ago)
   * @param long [optional] $maxTimestamp Media taken earlier than this timestamp (default: now)
   * @return mixed
   */
  public function searchMedia($lat, $lng, $distance = 1000, $minTimestamp = NULL, $maxTimestamp = NULL) {
    return $this->_makeRequest('media/search', array('lat' => $lat, 'lng' => $lng, 'distance' => $distance, 'min_timestamp' => $minTimestamp, 'max_timestamp' => $maxTimestamp));
  }

  /**
   * Get media by its id
   *
   * @param integer $id                   Instagram media ID
   * @return mixed
   */
  public function getMedia($id) {
    return $this->_makeRequest('media/' . $id);
  }


  /**
   * Get media by its shortcode
   *
   * @param string $shortcode  Instagram media by Shortcode
   * @return mixed
   */
  public function getMediaByShortcode($shortcode) {
    return $this->_makeRequest('media/shortcode' . $shortcode);
  }


  /**
   * Search for tags by name
   *
   * @param string $name                  Valid tag name
   * @return mixed
   */
  public function searchTags($name) {
    return $this->_makeRequest('tags/search', array('q' => $name));
  }

  /**
   * Get info about a tag
   *
   * @param string $name                  Valid tag name
   * @return mixed
   */
  public function getTag($name) {
    return $this->_makeRequest('tags/' . $name);
  }

  /**
   * Get a recently tagged media
   *
   * @param string $name                  Valid tag name
   * @param integer [optional] $limit     Limit of returned results
   * @param string $max_id
   * @param string $min_id
   * @return mixed
   */
  public function getTagMedia($name, $limit = null, $max_id = null, $min_id = null) {
    $params = array();
    if(!empty($limit) && is_numeric($limit))
      $params['count'] = $limit;
    if(!empty($max_id))
      $params['max_tag_id'] = $max_id;
    if(!empty($min_id))
      $params['min_tag_id'] = $min_id;
    return $this->_makeRequest('tags/' . $name . '/media/recent', $params);
  }

  /**
   * Get a list of users who have liked this media
   *
   * @param integer $id                   Instagram media ID
   * @return mixed
   */
  public function getMediaLikes($id) {
    return $this->_makeRequest('media/' . $id . '/likes');
  }

  /**
   * Get a list of comments for this media
   *
   * @param integer $id                   Instagram media ID
   * @return mixed
   */
  public function getMediaComments($id) {
    return $this->_makeRequest('media/' . $id . '/comments');
  }

  /**
   * Add a comment on a media
   *
   * @param integer $id                   Instagram media ID
   * @param string $text                  Comment content
   * @return mixed
   */
  public function addMediaComment($id, $text) {
    return $this->_makeRequest('media/' . $id . '/comments', array('text' => $text), self::HTTP_METHOD_POST);
  }

  /**
   * Remove user comment on a media
   *
   * @param integer $id                   Instagram media ID
   * @param string $comment_id             User comment ID
   * @return mixed
   */
  public function deleteMediaComment($id, $comment_id) {
    return $this->_makeRequest('media/' . $id . '/comments/' . $comment_id, null, self::HTTP_METHOD_DELETE);
  }

  /**
   * Set user like on a media
   *
   * @param integer $id                   Instagram media ID
   * @return mixed
   */
  public function likeMedia($id) {
    return $this->_makeRequest('media/' . $id . '/likes', null, self::HTTP_METHOD_POST);
  }

  /**
   * Remove user like on a media
   *
   * @param integer $id                   Instagram media ID
   * @return mixed
   */
  public function deleteLikedMedia($id) {
    return $this->_makeRequest('media/' . $id . '/likes', null, self::HTTP_METHOD_DELETE);
  }

  /**
   * Get information about a location
   *
   * @param integer $id                   Instagram location ID
   * @return mixed
   */
  public function getLocation($id) {
    return $this->_makeRequest('locations/' . $id);
  }

  /**
   * Get recent media from a given location
   *
   * @param integer $id                   Instagram location ID
   * @return mixed
   */
  public function getLocationMedia($id, $max_id = null, $min_id = null) {
    $params = array();
    if(!empty($max_id))
      $params['max_id'] = $max_id;
    if(!empty($min_id))
      $params['min_id'] = $min_id;
    return $this->_makeRequest('locations/' . $id . '/media/recent', $params);
  }

  /**
   * Get recent media from a given location
   *
   * @param float $lat                    Latitude of the center search coordinate
   * @param float $lng                    Longitude of the center search coordinate
   * @param integer [optional] $distance  Distance in meter (max. distance: 5km = 5000)
   * @return mixed
   */
  public function searchLocation($lat, $lng, $distance = 1000) {
    return $this->_makeRequest('locations/search', array('lat' => $lat, 'lng' => $lng, 'distance' => $distance));
  }


  /**
   * Get notified when people who authenticated your app post new media on Instagram
   *
   * Create new Subscription
   *
   * @param string $object
   * @param string $aspect
   * @param $verify_token
   * @param $callback_url
   * @return mixed
   * @throws Exception
   */
  public function createSubscriptions($object = 'user', $aspect = 'media', $verify_token, $callback_url)
  {
    if(!empty($verify_token) && !empty($callback_url)) {
      return $this->_makeRequest('subscriptions', array('client_id'=>$this->getApiKey(), 'client_secret'=>$this->getApiSecret(), 'object'=>$object, 'aspect'=>$aspect, 'verify_token'=>$verify_token, 'callback_url'=>$callback_url), self::HTTP_METHOD_POST);
    }
    throw new \Exception("Error: createSubscriptions() | This method requires verification token and callbackurl");
  }


  /**
   * Get list of Subscriptions
   *
   * @return mixed
   * @throws Exception
   */
  public function getSubscriptionsList()
  {
      return $this->_makeRequest('subscriptions', array('client_id'=>$this->getApiKey(), 'client_secret'=>$this->getApiSecret()));
  }


  /**
   * Delete Subscriptions by its Object
   *
   * @param string $object
   * @return mixed
   * @throws Exception
   */
  public function deleteSubscriptionsByObject($object = 'all')
  {
    return $this->_makeRequest('subscriptions', array('object'=>$object, 'client_id'=>$this->getApiKey(), 'client_secret'=>$this->getApiSecret()), self::HTTP_METHOD_DELETE);
  }


  /**
   * Delete Subscriptions by its Id
   * 
   * @param $id
   * @return mixed
   * @throws Exception
   */
  public function deleteSubscriptionsById($id)
  {
    return $this->_makeRequest('subscriptions', array( 'id'=>$id, 'client_id'=>$this->getApiKey(), 'client_secret'=>$this->getApiSecret()), self::HTTP_METHOD_DELETE);
  }

  /**
   * Get the OAuth data of a user by the returned callback code
   *
   * @param string $code                  OAuth2 code variable (after a successful login)
   * @param boolean [optional] $token     If it's true, only the access token will be returned
   * @return mixed
   */
  public function getOAuthToken($code, $token = false) {
    $apiData = array(
      'grant_type'      => 'authorization_code',
      'client_id'       => $this->getApiKey(),
      'client_secret'   => $this->getApiSecret(),
      'redirect_uri'    => $this->getApiCallback(),
      'code'            => $code
    );

    $result = $this->_makeOAuthCall($apiData);
    return (false === $token) ? $result : $result->access_token;
  }

  /**
   * The call operator
   *
   * @param string $function              API resource path
   * @param array [optional] $params      Additional request parameters
   * @param string [optional] $method     Request type GET|POST
   * @return mixed
   */
  protected function _makeRequest($function, $params = null, $method = 'GET') {
    if (true === isset($this->_accesstoken)) {
      $authMethod = '?access_token=' . $this->getAccessToken();
    } else {
      throw new \Exception("Error: _makeRequest() | $function - This method requires an authenticated users access token.");
    }

    if (isset($params) && is_array($params)) {
      $paramString = '&' . http_build_query($params);
    } else {
      $paramString = null;
    }

    $apiCall = self::API_URL . $function . $authMethod . (('GET' === $method) ? $paramString : null);

    // signed header of POST/DELETE requests
    $headerData = array('Accept: application/json');
    if (true === $this->_signedheader && 'GET' !== $method) {
      $headerData[] = 'X-Insta-Forwarded-For: ' . $this->_signHeader();
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiCall);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerData);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ('POST' === $method) {
      curl_setopt($ch, CURLOPT_POST, count($params));
      curl_setopt($ch, CURLOPT_POSTFIELDS, ltrim($paramString, '&'));
    } else if ('DELETE' === $method) {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $jsonData = curl_exec($ch);
    if (false === $jsonData) {
      throw new \Exception("Error: _makeRequest() - cURL error: " . curl_error($ch));
    }
    curl_close($ch);

    return json_decode($jsonData);
  }

  /**
   * The OAuth call operator
   *
   * @param array $apiData                The post API data
   * @return mixed
   */
  private function _makeOAuthCall($apiData) {
    $apiHost = self::API_OAUTH_TOKEN_URL;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiHost);
    curl_setopt($ch, CURLOPT_POST, count($apiData));
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $jsonData = curl_exec($ch);
    if (false === $jsonData) {
      throw new \Exception("Error: _makeOAuthCall() - cURL error: " . curl_error($ch));
    }
    curl_close($ch);

    return json_decode($jsonData);
  }

  /**
   * Sign header by using the app's IP and its API secret
   *
   * @return string                       The signed header
   */
  private function _signHeader() {
    $ipAddress = $_SERVER['SERVER_ADDR'];
    $signature = hash_hmac('sha256', $ipAddress, $this->_apisecret, false);
    return join('|', array($ipAddress, $signature));
  }

  /**
   * Access Token Setter
   *
   * @param object|string $data
   * @return void
   */
  public function setAccessToken($data) {
    (true === is_object($data)) ? $token = $data->access_token : $token = $data;
    $this->_accesstoken = $token;
  }

  /**
   * Access Token Getter
   *
   * @return string
   */
  public function getAccessToken() {
    return $this->_accesstoken;
  }

  /**
   * API-key Setter
   *
   * @param string $apiKey
   * @return void
   */
  public function setApiKey($apiKey) {
    $this->_apikey = $apiKey;
  }

  /**
   * API Key Getter
   *
   * @return string
   */
  public function getApiKey() {
    return $this->_apikey;
  }

  /**
   * API Secret Setter
   *
   * @param string $apiSecret 
   * @return void
   */
  public function setApiSecret($apiSecret) {
    $this->_apisecret = $apiSecret;
  }

  /**
   * API Secret Getter
   *
   * @return string
   */
  public function getApiSecret() {
    return $this->_apisecret;
  }
  
  /**
   * API Callback URL Setter
   *
   * @param string $apiCallback
   * @return void
   */
  public function setApiCallback($apiCallback) {
    $this->_callbackurl = $apiCallback;
  }

  /**
   * API Callback URL Getter
   *
   * @return string
   */
  public function getApiCallback() {
    return $this->_callbackurl;
  }

  /**
   * Enforce Signed Header
   *
   * @param boolean $signedHeader
   * @return void
   */
  public function setSignedHeader($signedHeader) {
    $this->_signedheader = $signedHeader;
  }


}
