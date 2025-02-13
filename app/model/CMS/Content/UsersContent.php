<?php

declare(strict_types=1);

namespace App\Model\CMS\Content;

use App\Model\ACL\Role;
use App\Model\ACL\RoleRepository;
use App\Model\CMS\Page;
use App\Model\Page\PageException;
use App\Utils\Helpers;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nette\Application\UI\Form;

/**
 * Entita obsahu se seznamem uživatelů.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 * @ORM\Entity
 * @ORM\Table(name="users_content")
 */
class UsersContent extends Content implements IContent
{
    /** @var string */
    protected $type = Content::USERS;

    /**
     * Role, jejichž uživatelé budou vypsáni.
     * @ORM\ManyToMany(targetEntity="\App\Model\ACL\Role")
     * @var Collection|Role[]
     */
    protected $roles;

    /** @var RoleRepository */
    private $roleRepository;


    /**
     * @throws PageException
     */
    public function __construct(Page $page, string $area)
    {
        parent::__construct($page, $area);
        $this->roles = new ArrayCollection();
    }

    public function injectRoleRepository(RoleRepository $roleRepository) : void
    {
        $this->roleRepository = $roleRepository;
    }

    /**
     * @return Collection|Role[]
     */
    public function getRoles() : Collection
    {
        return $this->roles;
    }

    /**
     * @param Collection|Role[] $roles
     */
    public function setRoles(Collection $roles) : void
    {
        $this->roles->clear();
        foreach ($roles as $role) {
            $this->roles->add($role);
        }
    }

    /**
     * Přidá do formuláře pro editaci stránky formulář pro úpravu obsahu.
     */
    public function addContentForm(Form $form) : Form
    {
        parent::addContentForm($form);

        $formContainer = $form[$this->getContentFormName()];

        $formContainer->addMultiSelect(
            'roles',
            'admin.cms.pages_content_users_roles',
            $this->roleRepository->getRolesWithoutRolesOptions([Role::GUEST, Role::UNAPPROVED, Role::NONREGISTERED])
        )
            ->setDefaultValue($this->roleRepository->findRolesIds($this->roles));

        return $form;
    }

    /**
     * Zpracuje při uložení stránky část formuláře týkající se obsahu.
     */
    public function contentFormSucceeded(Form $form, \stdClass $values) : void
    {
        parent::contentFormSucceeded($form, $values);
        $values      = $values[$this->getContentFormName()];
        $this->roles = $this->roleRepository->findRolesByIds($values['roles']);
    }

    public function convertToDTO() : ContentDTO
    {
        return new UsersContentDTO($this->getComponentName(), $this->heading, Helpers::getIds($this->roles));
    }
}
