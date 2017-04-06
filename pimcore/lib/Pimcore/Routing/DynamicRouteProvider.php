<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Routing;

use Pimcore\Config;
use Pimcore\Http\RequestHelper;
use Pimcore\Model\Document;
use Pimcore\Routing\Dynamic\DynamicRequestContext;
use Pimcore\Routing\Dynamic\DynamicRouteHandler;
use Pimcore\Service\Document\NearestPathResolver;
use Pimcore\Service\MvcConfigNormalizer;
use Pimcore\Service\Request\SiteResolver;
use Symfony\Cmf\Component\Routing\RouteProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouteCollection;

class DynamicRouteProvider implements RouteProviderInterface
{
    /**
     * @var SiteResolver
     */
    protected $siteResolver;

    /**
     * @var DynamicRouteHandler[]
     */
    protected $handlers = [];

    /**
     * @param SiteResolver $siteResolver
     * @param DynamicRouteHandler[] $handlers
     */
    public function __construct(SiteResolver $siteResolver, array $handlers)
    {
        $this->siteResolver = $siteResolver;
        $this->handlers     = $handlers;
    }

    /**
     * @inheritdoc
     */
    public function getRouteCollectionForRequest(Request $request)
    {
        $collection = new RouteCollection();
        $path       = $originalPath = urldecode($request->getPathInfo());

        // site path handled by FrontendRoutingListener which runs before routing is started
        if (null !== $sitePath = $this->siteResolver->getSitePath($request)) {
            $path = $sitePath;
        }

        $context = new DynamicRequestContext($request, $path, $originalPath);

        foreach ($this->handlers as $handler) {
            $handler->matchRequest($collection, $context);
        }

        return $collection;
    }

    /**
     * @inheritdoc
     */
    public function getRouteByName($name)
    {
        foreach ($this->handlers as $handler) {
            try {
                return $handler->getRouteByName($name);
            } catch (RouteNotFoundException $e) {
                // noop
            }
        }

        throw new RouteNotFoundException(sprintf("Route for name '%s' was not found", $name));
    }

    /**
     * @inheritdoc
     */
    public function getRoutesByNames($names)
    {
        // TODO needs performance optimizations
        // TODO really return all routes here as documentation states? where is this used?
        $routes = [];

        if (is_array($names)) {
            foreach ($names as $name) {
                try {
                    $route = $this->getRouteByName($name);
                    if ($route) {
                        $routes[] = $route;
                    }
                } catch (RouteNotFoundException $e) {
                    // noop
                }
            }
        }

        return $routes;
    }
}
