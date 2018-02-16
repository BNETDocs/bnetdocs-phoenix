<?php

namespace BNETDocs\Controllers\User;

use \BNETDocs\Libraries\User;
use \BNETDocs\Models\User\Index as UserIndexModel;
use \BNETDocs\Views\User\IndexHtml as UserIndexHtmlView;
use \BNETDocs\Views\User\IndexJSON as UserIndexJSONView;
use \CarlBennett\MVC\Libraries\Common;
use \CarlBennett\MVC\Libraries\Controller;
use \CarlBennett\MVC\Libraries\Gravatar;
use \CarlBennett\MVC\Libraries\Router;
use \CarlBennett\MVC\Libraries\View;

class Index extends Controller {

  const PAGINATION_LIMIT_DEF = 20;  // The default amount of items per page.
  const PAGINATION_LIMIT_MIN = 5;   // The least amount of items per page.
  const PAGINATION_LIMIT_MAX = 250; // The most amount of items per page.

  public function &run(Router &$router, View &$view, array &$args) {

    $model = new UserIndexModel();

    $query = $router->getRequestQueryArray();

    $model->page = (isset($query['page']) ? (int) $query['page'] : 0);

    $model->limit = (
      isset($query['limit']) ?
      (int) $query['limit'] : self::PAGINATION_LIMIT_DEF
    );

    if ($model->page < 1) { $model->page = 1; }

    if ($model->limit < self::PAGINATION_LIMIT_MIN) {
      $model->limit = self::PAGINATION_LIMIT_MIN;
    }

    if ($model->limit > self::PAGINATION_LIMIT_MAX) {
      $model->limit = self::PAGINATION_LIMIT_MAX;
    }

    $model->pages = ceil(User::getUserCount() / $model->limit);

    if ($model->page > $model->pages) { $model->page = $model->pages; }

    $model->users = User::getAllUsers(
      false, // reverse
      $model->limit,
      $model->limit * ( $model->page - 1 )
    );

    /*for ($i = 101; $i <= 100000; ++$i) {
      $email           = 'testuser' . $i . '@bnetdocs.org';
      $username        = 'testuser' . $i;
      $display_name    = null;
      $password        = 'testuser';
      $options_bitmask = User::OPTION_DISABLED;

      User::create(
        $email, $username, $display_name, $password, $options_bitmask
      );
    }*/

    // Post-filter summary of users
    $model->sum_users = count($model->users);

    $view->render($model);

    $model->_responseCode = 200;
    $model->_responseHeaders['Content-Type'] = $view->getMimeType();
    $model->_responseTTL = 0;

    return $model;

  }

}