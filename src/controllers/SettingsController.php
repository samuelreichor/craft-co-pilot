<?php

namespace samuelreichor\coPilot\controllers;

use Craft;
use craft\helpers\Cp;
use craft\web\Controller;
use samuelreichor\coPilot\constants\Constants;
use samuelreichor\coPilot\CoPilot;
use yii\web\Response;

class SettingsController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Constants::PERMISSION_VIEW_BRAND_VOICE);

        return true;
    }

    /**
     * GET /admin/co-pilot/brand-voice
     */
    public function actionBrandVoice(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();

        return $this->renderTemplate('co-pilot/settings/brand-voice', [
            'settings' => CoPilot::getInstance()->getSettings(),
            'canEdit' => $user && $user->can(Constants::PERMISSION_EDIT_BRAND_VOICE),
            'selectedSite' => Cp::requestedSite(),
        ]);
    }

    /**
     * POST /actions/co-pilot/settings/save
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Constants::PERMISSION_EDIT_BRAND_VOICE);

        $plugin = CoPilot::getInstance();
        $settings = $plugin->getSettings();

        $settings->brandVoice = $this->request->getBodyParam('brandVoice', $settings->brandVoice);
        $settings->glossary = $this->request->getBodyParam('glossary', $settings->glossary);
        $settings->forbiddenWords = $this->request->getBodyParam('forbiddenWords', $settings->forbiddenWords);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            Craft::$app->getSession()->setError('Couldn\'t save plugin settings.');

            return null;
        }

        $plugin->schemaService->invalidateCache();

        Craft::$app->getSession()->setNotice('Plugin settings saved.');

        return $this->redirectToPostedUrl();
    }
}
