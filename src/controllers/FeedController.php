<?php

namespace thekitchenagency\craftcommerceproductfeed\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use thekitchenagency\craftcommerceproductfeed\CommerceProductFeed;

/**
 * Controller to expose dynamic public feeds.
 */
class FeedController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = true;

    /**
     * Expose public Google Shopping XML feed.
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionGoogle(): Response
    {
        $nocache = (bool)Craft::$app->getRequest()->getQueryParam('nocache', false);
        $content = CommerceProductFeed::getInstance()->getFeedService()->getFeedContent('google', $nocache);

        if ($content === null) {
            throw new NotFoundHttpException('Google Shopping feed is disabled or not generated.');
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Cache-Control', 'public, max-age=900');
        $response->content = $content;

        return $response;
    }

    /**
     * Expose public Meta Catalog XML feed.
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionMeta(): Response
    {
        $nocache = (bool)Craft::$app->getRequest()->getQueryParam('nocache', false);
        $content = CommerceProductFeed::getInstance()->getFeedService()->getFeedContent('meta', $nocache);

        if ($content === null) {
            throw new NotFoundHttpException('Meta Catalog feed is disabled or not generated.');
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Cache-Control', 'public, max-age=900');
        $response->content = $content;

        return $response;
    }

    /**
     * Expose public Pinterest Catalog XML feed.
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionPinterest(): Response
    {
        $nocache = (bool)Craft::$app->getRequest()->getQueryParam('nocache', false);
        $content = CommerceProductFeed::getInstance()->getFeedService()->getFeedContent('pinterest', $nocache);

        if ($content === null) {
            throw new NotFoundHttpException('Pinterest Catalog feed is disabled or not generated.');
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Cache-Control', 'public, max-age=900');
        $response->content = $content;

        return $response;
    }

    /**
     * Expose public TikTok Shop XML feed.
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionTiktok(): Response
    {
        $nocache = (bool)Craft::$app->getRequest()->getQueryParam('nocache', false);
        $content = CommerceProductFeed::getInstance()->getFeedService()->getFeedContent('tiktok', $nocache);

        if ($content === null) {
            throw new NotFoundHttpException('TikTok Shop feed is disabled or not generated.');
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Cache-Control', 'public, max-age=900');
        $response->content = $content;

        return $response;
    }

    /**
     * Expose public OpenAI JSON feed (paginated).
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionOpenai(): Response
    {
        $nocache = (bool)Craft::$app->getRequest()->getQueryParam('nocache', false);
        $page = (int)Craft::$app->getRequest()->getQueryParam('page', 1);
        $perPage = (int)Craft::$app->getRequest()->getQueryParam('per_page', 100);

        $content = CommerceProductFeed::getInstance()->getFeedService()->getFeedContent('openai', $nocache);

        if ($content === null) {
            throw new NotFoundHttpException('OpenAI feed is disabled or not generated.');
        }

        // Apply pagination on decoded payload
        $feedData = json_decode($content, true);
        if (is_array($feedData) && isset($feedData['products'])) {
            $totalProducts = count($feedData['products']);
            $offset = ($page - 1) * $perPage;
            $feedData['products'] = array_slice($feedData['products'], $offset, $perPage);
            
            $feedData['pagination'] = [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalProducts,
                'total_pages' => (int)ceil($totalProducts / $perPage)
            ];

            $content = json_encode($feedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $response->headers->set('Cache-Control', 'public, max-age=900');
        $response->content = $content;

        return $response;
    }

    /**
     * Expose public Ricardo.ch JSON feed.
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionRicardo(): Response
    {
        $nocache = (bool)Craft::$app->getRequest()->getQueryParam('nocache', false);
        $content = CommerceProductFeed::getInstance()->getFeedService()->getFeedContent('ricardo', $nocache);

        if ($content === null) {
            throw new NotFoundHttpException('Ricardo.ch feed is disabled or not generated.');
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $response->headers->set('Cache-Control', 'public, max-age=900');
        $response->content = $content;

        return $response;
    }

    /**
     * Expose public generic CSV product feed.
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionCsv(): Response
    {
        $nocache = (bool)Craft::$app->getRequest()->getQueryParam('nocache', false);
        $content = CommerceProductFeed::getInstance()->getFeedService()->getFeedContent('csv', $nocache);

        if ($content === null) {
            throw new NotFoundHttpException('CSV feed is disabled or not generated.');
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="product-feed.csv"');
        $response->headers->set('Cache-Control', 'public, max-age=900');
        $response->content = $content;

        return $response;
    }

    /**
     * Expose public raw products details feed for developer debugging.
     *
     * @return Response
     */
    public function actionProducts(): Response
    {
        $nocache = (bool)Craft::$app->getRequest()->getQueryParam('nocache', false);
        $page = (int)Craft::$app->getRequest()->getQueryParam('page', 1);
        $perPage = (int)Craft::$app->getRequest()->getQueryParam('per_page', 50);

        $content = CommerceProductFeed::getInstance()->getFeedService()->getRawProductsJson($nocache);

        // Apply pagination on decoded payload
        $feedData = json_decode($content, true);
        if (is_array($feedData) && isset($feedData['products'])) {
            $totalProducts = count($feedData['products']);
            $offset = ($page - 1) * $perPage;
            $feedData['products'] = array_slice($feedData['products'], $offset, $perPage);
            
            $feedData['pagination'] = [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalProducts,
                'total_pages' => (int)ceil($totalProducts / $perPage)
            ];

            $content = json_encode($feedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $response->headers->set('Cache-Control', 'public, max-age=900');
        $response->content = $content;

        return $response;
    }

    /**
     * Action to clear the cache manually from administrative button.
     *
     * @return Response
     */
    public function actionClearCache(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('admin'); // Standard admin check

        CommerceProductFeed::getInstance()->getFeedService()->clearCache();
        Craft::$app->getSession()->setNotice('Feed cache cleared successfully.');

        return $this->redirectToPostedUrl();
    }
}
