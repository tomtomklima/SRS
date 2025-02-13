<?php

declare(strict_types=1);

namespace App\WebModule\Presenters;

use App\Model\ACL\Permission;
use App\Model\ACL\Resource;
use App\Model\ACL\ResourceRepository;
use App\Model\ACL\Role;
use App\Model\ACL\RoleRepository;
use App\Model\CMS\PageRepository;
use App\Model\Settings\Settings;
use App\Model\Settings\SettingsException;
use App\Model\Settings\SettingsRepository;
use App\Model\User\User;
use App\Model\User\UserRepository;
use App\Presenters\BasePresenter;
use App\Services\Authenticator;
use App\Services\Authorizator;
use App\Services\DatabaseService;
use App\Services\SkautIsService;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Nette\Application\AbortException;
use WebLoader\Nette\CssLoader;
use WebLoader\Nette\JavaScriptLoader;

/**
 * BasePresenter pro WebModule.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
abstract class WebBasePresenter extends BasePresenter
{
    /**
     * @var Authorizator
     * @inject
     */
    public $authorizator;

    /**
     * @var Authenticator
     * @inject
     */
    public $authenticator;

    /**
     * @var ResourceRepository
     * @inject
     */
    public $resourceRepository;

    /**
     * @var RoleRepository
     * @inject
     */
    public $roleRepository;

    /**
     * @var PageRepository
     * @inject
     */
    public $pageRepository;

    /**
     * @var SettingsRepository
     * @inject
     */
    public $settingsRepository;

    /**
     * @var UserRepository
     * @inject
     */
    public $userRepository;

    /**
     * @var SkautIsService
     * @inject
     */
    public $skautIsService;

    /**
     * @var DatabaseService
     * @inject
     */
    public $databaseService;

    /** @var User */
    protected $dbuser;


    /**
     * Načte css podle konfigurace v config.neon.
     */
    protected function createComponentCss() : CssLoader
    {
        return $this->webLoader->createCssLoader('web');
    }

    /**
     * Načte javascript podle konfigurace v config.neon.
     */
    protected function createComponentJs() : JavaScriptLoader
    {
        return $this->webLoader->createJavaScriptLoader('web');
    }

    /**
     * @throws AbortException
     * @throws \Throwable
     */
    public function startup() : void
    {
        parent::startup();

        $this->checkInstallation();

        if ($this->user->isLoggedIn() && ! $this->skautIsService->isLoggedIn()) {
            $this->user->logout(true);
        }

        $this->user->setAuthorizator($this->authorizator);

        $this->dbuser = $this->user->isLoggedIn() ? $this->userRepository->findById($this->user->id) : null;
    }

    /**
     * @throws SettingsException
     * @throws \Throwable
     */
    public function beforeRender() : void
    {
        parent::beforeRender();

        $this->template->dbuser = $this->dbuser;

        $this->template->backlink = $this->getHttpRequest()->getUrl()->getPath();

        $this->template->logo        = $this->settingsRepository->getValue(Settings::LOGO);
        $this->template->footer      = $this->settingsRepository->getValue(Settings::FOOTER);
        $this->template->seminarName = $this->settingsRepository->getValue(Settings::SEMINAR_NAME);
        $this->template->gaId        = $this->settingsRepository->getValue(Settings::GA_ID);

        $this->template->nonregisteredRole = $this->roleRepository->findBySystemName(Role::NONREGISTERED);
        $this->template->unapprovedRole    = $this->roleRepository->findBySystemName(Role::UNAPPROVED);
        $this->template->testRole          = Role::TEST;

        $this->template->adminAccess = $this->user->isAllowed(Resource::ADMIN, Permission::ACCESS);

        $this->template->pages          = $this->pageRepository->findPublishedOrderedByPositionDTO();
        $this->template->sidebarVisible = false;

        $this->template->settings = $this->settingsRepository;
    }

    /**
     * Ukončí testování role.
     * @throws AbortException
     */
    public function actionExitRoleTest() : void
    {
        $this->authenticator->updateRoles($this->user);
        $this->redirect(':Admin:Acl:default');
    }

    /**
     * Zkontroluje stav instalace.
     * @throws AbortException
     * @throws \Throwable
     */
    private function checkInstallation() : void
    {
        try {
            if (! $this->settingsRepository->getBoolValue(Settings::ADMIN_CREATED)) {
                $this->redirect(':Install:Install:default');
            } else {
                $this->databaseService->update();
            }
        } catch (TableNotFoundException $ex) {
            $this->redirect(':Install:Install:default');
        } catch (SettingsException $ex) {
            $this->redirect(':Install:Install:default');
        }
    }
}
