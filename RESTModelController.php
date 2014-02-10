<?php

namespace MABI;

include_once __DIR__ . '/Inflector.php';
include_once __DIR__ . '/Utilities.php';
include_once __DIR__ . '/ModelController.php';

/**
 * todo: docs
 */
class RESTModelController extends ModelController {

  public function __construct($extension) {
    parent::__construct($extension);

    $systemCache = $this->getApp()->getCacheRepository('system');
    $cacheKey = get_called_class() . get_class() . '::__construct';
    if($systemCache != null && ($cache = $systemCache->get($cacheKey)) != null) {
      $this->documentationName = $cache;
      return;
    }

    // If this was an autogenerated RESTModelController instead of an overridden one, the name in the documentation
    // should be based on the Model class, unless it was changed on the overrride
    if (get_called_class() == 'MABI\RESTModelController') {
      $this->documentationName = ucwords(ReflectionHelper::stripClassName($this->modelClass));
    }

    if($systemCache != null) {
      $systemCache->forever($cacheKey, $this->documentationName);
    }
  }

  public function get() {
    $outputArr = array();
    foreach ($this->model->findAll() as $foundModel) {
      $outputArr[] = $foundModel->getPropertyArray(TRUE);
    }
    echo json_encode($outputArr);
  }

  public function post() {
    $this->model->loadFromExternalSource($this->getApp()->getRequest()->getBody());

    if ($this->model->findById($this->model->getId())) {
      $this->getApp()->returnError(DefaultAppErrors::$ENTRY_EXISTS, array('!modelid' => $this->model->getId()));
    }

    $this->model->insert();
    echo $this->model->outputJSON();
  }

  public function put() {
    // todo: implement
  }

  public function delete() {
    // todo: implement
  }

  public function _restGetResource($id) {
    /**
     * @var $model Model
     */
    echo $this->model->outputJSON();
  }

  /**
   * @param $id
   *
   * @docs-param body string body required The object to update in the database
   */
  public function _restPutResource($id) {
    $this->model->loadFromExternalSource($this->getApp()->getRequest()->getBody());
    $this->model->setId($id);

    $this->model->save();
    echo $this->model->outputJSON();
  }

  public function _restDeleteResource($id) {
    $this->model->delete();
    echo $this->model->outputJSON();
  }

  /**
   * @param $route \Slim\Route
   */
  public function _readModel($route) {
    $this->model->findById($route->getParam('id'));
  }

  private function mapRestRoute(\Slim\Slim $slim, $path, $methodName, $httpMethod, &$cachedRoutes = NULL) {
    $slim->map($path,
      array($this, 'preMiddleware'),
      array($this, '_readModel'),
      array($this, '_runControllerMiddlewares'),
      array($this, 'preCallable'),
      array($this, $methodName))->via($httpMethod);

    if (is_array($cachedRoutes)) {
      $cachedRoutes[] = new CachedRoute($path, $methodName, $httpMethod);
    }
  }

  protected function addStandardRestRoute(\Slim\Slim $slim, $httpMethod) {
    $methodName = '_rest' . ucwords(strtolower($httpMethod)) . 'Resource';

    $rMethod = new \ReflectionMethod(get_called_class(), $methodName);
    // If there is a '@endpoint ignore' property, the function is not served as an endpoint
    if (in_array('ignore', ReflectionHelper::getDocDirective($rMethod->getDocComment(), 'endpoint'))) {
      return;
    }

    $this->mapRestRoute($slim, "/{$this->base}/:id(/?)", $methodName, $httpMethod);
  }

  /**
   * @param $slim \Slim\Slim
   */
  public function loadRoutes($slim) {
    parent::loadRoutes($slim);

    /**
     * @var $cachedRoutes CachedRoute[]
     */
    $cacheKey = get_called_class() . '.' . get_class() . '::loadRoutes';
    if (($systemCache = $this->getApp()->getCacheRepository('system')) != NULL &&
      is_array($cachedRoutes = $systemCache->get($cacheKey))) {
      // Get routes from cache
      foreach($cachedRoutes as $cachedRoute) {
        $this->mapRestRoute($slim, $cachedRoute->path, $cachedRoute->method, $cachedRoute->httpMethod);
      }
      return;
    } else {
      $cachedRoutes = array();
    }

    /**
     * Automatically generates routes for the following
     *
     * GET      /<model>          get all models by id
     * POST     /<model>          creates a new model
     * PUT      /<model>          bulk creates full new model collection
     * DELETE   /<model>          deletes all models
     * GET      /<model>/<id>     gets one model's full details
     * PUT      /<model>/<id>     updates the model
     * DELETE   /<model>/<id>     deletes the model
     */

    // todo: add API versioning
    $this->addStandardRestRoute($slim, \Slim\Http\Request::METHOD_GET, $cachedRoutes);
    $this->addStandardRestRoute($slim, \Slim\Http\Request::METHOD_PUT, $cachedRoutes);
    $this->addStandardRestRoute($slim, \Slim\Http\Request::METHOD_DELETE, $cachedRoutes);

    /**
     * Gets other automatically generated routes following the pattern:
     * /BASE/:id/ACTION(/:param+) from methods named rest<METHOD><ACTION>()
     * where <METHOD> is GET, PUT, POST, or DELETE
     */
    $rClass = new \ReflectionClass($this);
    $rMethods = $rClass->getMethods(\ReflectionMethod::IS_PUBLIC);
    foreach ($rMethods as $rMethod) {
      // If there is a '@endpoint ignore' property, the function is not served as an endpoint
      if (in_array('ignore', ReflectionHelper::getDocDirective($rMethod->getDocComment(), 'endpoint'))) {
        continue;
      }

      $action = NULL;
      $httpMethod = NULL;
      $methodName = $rMethod->name;
      if (strpos($methodName, 'restGet', 0) === 0) {
        $action = strtolower(substr($methodName, 7));
        $httpMethod = \Slim\Http\Request::METHOD_GET;
      }
      elseif (strpos($methodName, 'restPut', 0) === 0) {
        $action = strtolower(substr($methodName, 7));
        $httpMethod = \Slim\Http\Request::METHOD_PUT;
      }
      elseif (strpos($methodName, 'restPost', 0) === 0) {
        $action = strtolower(substr($methodName, 8));
        $httpMethod = \Slim\Http\Request::METHOD_POST;
      }
      elseif (strpos($methodName, 'restDelete', 0) === 0) {
        $action = strtolower(substr($methodName, 10));
        $httpMethod = \Slim\Http\Request::METHOD_DELETE;
      }

      if (!empty($action)) {
        $this->mapRestRoute($slim, "/{$this->base}/:id/{$action}(/?)", $methodName, $httpMethod, $cachedRoutes);
        $this->mapRestRoute($slim, "/{$this->base}/:id/{$action}(/:param+)(/?)", $methodName, $httpMethod, $cachedRoutes);
      }
    }

    if ($systemCache != NULL) {
      $systemCache->forever($cacheKey, $cachedRoutes);
    }
  }

  private function getRestMethodDocJSON(Parser $parser, $methodName, $httpMethod, $url, $rClass, $method) {
    $methodDoc = array();

    $rMethod = new \ReflectionMethod(get_called_class(), $method);
    $docComment = $rMethod->getDocComment();
    // If there is a '@endpoint ignore' property, the function is not served as an endpoint
    if (in_array('ignore', ReflectionHelper::getDocDirective($docComment, 'endpoint'))) {
      return $methodDoc;
    }

    $methodDoc['InternalMethodName'] = $method;
    $methodDoc['MethodName'] = $methodName;
    $methodDoc['HTTPMethod'] = $httpMethod;
    $methodDoc['URI'] = $url;
    $methodDoc['Synopsis'] = $parser->parse(ReflectionHelper::getDocText($docComment));
    $methodDoc['parameters'] = $this->getDocParameters($rMethod);
    $methodDoc['parameters'][] = array(
      'Name' => 'id',
      'Required' => 'Y',
      'Type' => 'string',
      'Location' => 'url',
      'Description' => 'The id of the resource'
    );

    // Allow controller middlewares to modify the documentation for this method
    if (!empty($this->middlewares)) {
      $middleware = reset($this->middlewares);
      $middleware->documentMethod($rClass, $rMethod, $methodDoc);
    }

    return $methodDoc;
  }

  /**
   * todo: docs
   *
   * @param Parser $parser
   *
   * @endpoint ignore
   * @return array
   */
  public function getDocJSON(Parser $parser) {
    $doc = parent::getDocJSON($parser);

    $rClass = new \ReflectionClass(get_called_class());

    foreach($doc['methods'] as $k => $methodDoc) {
      if($methodDoc['InternalMethodName'] == 'get') {
        $doc['methods'][$k]['MethodName'] = 'Get Full Collection';
      }
      elseif($methodDoc['InternalMethodName'] == 'post') {
        $doc['methods'][$k]['MethodName'] = 'Add to Collection';
      }
      elseif($methodDoc['InternalMethodName'] == 'put') {
        $doc['methods'][$k]['MethodName'] = 'Replace Full Collection';
      }
      elseif($methodDoc['InternalMethodName'] == 'delete') {
        $doc['methods'][$k]['MethodName'] = 'Delete Full Collection';
      }
    }

    $methodDoc = $this->getRestMethodDocJSON($parser, 'Get Resource',
      'GET', "/{$this->base}/:id", $rClass, '_restGetResource');
    if (!empty($methodDoc)) {
      $doc['methods'][] = $methodDoc;
    }
    $methodDoc = $this->getRestMethodDocJSON($parser, 'Update Resource',
      'PUT', "/{$this->base}/:id", $rClass, '_restPutResource');
    if (!empty($methodDoc)) {
      $doc['methods'][] = $methodDoc;
    }
    $methodDoc = $this->getRestMethodDocJSON($parser, 'Delete Resource',
      'DELETE', "/{$this->base}/:id", $rClass, '_restDeleteResource');
    if (!empty($methodDoc)) {
      $doc['methods'][] = $methodDoc;
    }

    // Add documentation for custom rest actions
    $rMethods = $rClass->getMethods(\ReflectionMethod::IS_PUBLIC);
    foreach ($rMethods as $rMethod) {
      $docComment = $rMethod->getDocComment();
      // If there is a '@endpoint ignore' property, the function is not served as an endpoint
      if (in_array('ignore', ReflectionHelper::getDocDirective($docComment, 'endpoint'))) {
        continue;
      }

      $methodDoc = array();

      $methodDoc['InternalMethodName'] = $rMethod->name;
      if (strpos($rMethod->name, 'restGet', 0) === 0) {
        $methodDoc['MethodName'] = substr($rMethod->name, 7);
        $methodDoc['HTTPMethod'] = 'GET';
      }
      elseif (strpos($rMethod->name, 'restPut', 0) === 0) {
        $methodDoc['MethodName'] = substr($rMethod->name, 7);
        $methodDoc['HTTPMethod'] = 'PUT';
      }
      elseif (strpos($rMethod->name, 'restPost', 0) === 0) {
        $methodDoc['MethodName'] = substr($rMethod->name, 8);
        $methodDoc['HTTPMethod'] = 'POST';
      }
      elseif (strpos($rMethod->name, 'restDelete', 0) === 0) {
        $methodDoc['MethodName'] = substr($rMethod->name, 10);
        $methodDoc['HTTPMethod'] = 'DELETE';
      }
      else {
        continue;
      }
      $action = strtolower($methodDoc['MethodName']);
      $methodDoc['URI'] = "/{$this->base}/:id/{$action}";
      $methodDoc['Synopsis'] = $parser->parse(ReflectionHelper::getDocText($docComment));
      $methodDoc['parameters'][] = array(
        'Name' => 'id',
        'Required' => 'Y',
        'Type' => 'string',
        'Location' => 'url',
        'Description' => 'The id of the resource'
      );
      $methodDoc['parameters'] = array_merge($methodDoc['parameters'], $this->getDocParameters($rMethod));

      // Allow controller middlewares to modify the documentation for this method
      if (!empty($this->middlewares)) {
        $middleware = reset($this->middlewares);
        $middleware->documentMethod($rClass, $rMethod, $methodDoc);
      }

      if (!empty($methodDoc)) {
        $doc['methods'][] = $methodDoc;
      }
    }

    return $doc;
  }
}