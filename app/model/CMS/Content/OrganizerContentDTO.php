<?php

declare(strict_types=1);

namespace App\Model\CMS\Content;

/**
 * DTO obsahu s informací o pořadateli.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class OrganizerContentDTO extends ContentDTO
{
    /**
     * Pořadatel.
     * @var string
     */
    protected $organizer;


    public function __construct(string $type, string $heading, ?string $organizer)
    {
        parent::__construct($type, $heading);
        $this->organizer = $organizer;
    }

    public function getOrganizer() : ?string
    {
        return $this->organizer;
    }
}
