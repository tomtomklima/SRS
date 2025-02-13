<?php

declare(strict_types=1);

namespace App\Model\CMS;

use App\Model\CMS\Content\ContentDTO;

class PageDTO
{
    /**
     * Název stránky.
     * @var string
     */
    protected $name;

    /**
     * Cesta stránky.
     * @var string
     */
    protected $slug;

    /**
     * Role, které mají na stránku přístup.
     * @var string[]
     */
    protected $allowedRoles;

    /**
     * Obsahy v hlavní části stránky.
     * @var ContentDTO[]
     */
    protected $mainContents;

    /**
     * Obsahy v postranní části stránky.
     * @var ContentDTO[]
     */
    protected $sidebarContents;

    /**
     * Má stránka sidebar?
     * @var bool
     */
    protected $hasSidebar;


    /**
     * @param string[]     $allowedRoles
     * @param ContentDTO[] $mainContents
     * @param ContentDTO[] $sidebarContents
     */
    public function __construct(string $name, string $slug, array $allowedRoles, array $mainContents, array $sidebarContents, bool $hasSidebar)
    {
        $this->name            = $name;
        $this->slug            = $slug;
        $this->allowedRoles    = $allowedRoles;
        $this->mainContents    = $mainContents;
        $this->sidebarContents = $sidebarContents;
        $this->hasSidebar      = $hasSidebar;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getSlug() : string
    {
        return $this->slug;
    }

    /**
     * @return string[]
     */
    public function getAllowedRoles() : array
    {
        return $this->allowedRoles;
    }

    /**
     * @return ContentDTO[]
     */
    public function getMainContents() : array
    {
        return $this->mainContents;
    }

    /**
     * @return ContentDTO[]
     */
    public function getSidebarContents() : array
    {
        return $this->sidebarContents;
    }

    public function hasSidebar() : bool
    {
        return $this->hasSidebar;
    }

    /**
     * Je stránka viditelná pro uživatele v rolích?
     * @param string[] $userRoles
     */
    public function isAllowedForRoles(array $userRoles) : bool
    {
        foreach ($userRoles as $userRole) {
            foreach ($this->allowedRoles as $allowedRole) {
                if ($userRole === $allowedRole) {
                    return true;
                }
            }
        }
        return false;
    }
}
