<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\ACL\Role;
use App\Model\Program\Block;
use App\Model\Program\BlockRepository;
use App\Model\Program\CategoryRepository;
use App\Model\Program\ProgramRepository;
use App\Model\Program\Room;
use App\Model\Settings\CustomInput\CustomInput;
use App\Model\Settings\CustomInput\CustomInputRepository;
use App\Model\Structure\SubeventRepository;
use App\Model\User\User;
use App\Utils\Helpers;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Translation\Translator;
use Nette;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use function implode;
use function preg_replace;

/**
 * Služba pro export do formátu XLSX.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class ExcelExportService
{
    use Nette\SmartObject;

    /** @var Spreadsheet */
    private $spreadsheet;

    /** @var Translator */
    private $translator;

    /** @var CustomInputRepository */
    private $customInputRepository;

    /** @var BlockRepository */
    private $blockRepository;

    /** @var UserService */
    private $userService;

    /** @var SubeventRepository */
    private $subeventRepository;

    /** @var CategoryRepository */
    private $categoryRepository;

    /** @var ProgramRepository */
    private $programRepository;

    /** @var ProgramService */
    private $programService;


    public function __construct(
        Translator $translator,
        CustomInputRepository $customInputRepository,
        BlockRepository $blockRepository,
        UserService $userService,
        SubeventRepository $subeventRepository,
        CategoryRepository $categoryRepository,
        ProgramRepository $programRepository,
        ProgramService $programService
    ) {
        $this->spreadsheet = new Spreadsheet();

        $this->translator            = $translator;
        $this->customInputRepository = $customInputRepository;
        $this->blockRepository       = $blockRepository;
        $this->userService           = $userService;
        $this->subeventRepository    = $subeventRepository;
        $this->categoryRepository    = $categoryRepository;
        $this->programRepository     = $programRepository;
        $this->programService        = $programService;
    }

    /**
     * Vyexportuje matici uživatelů a rolí.
     * @param Collection|User[] $users
     * @param Collection|Role[] $roles
     * @throws Exception
     */
    public function exportUsersRoles(Collection $users, Collection $roles, string $filename) : ExcelResponse
    {
        $sheet = $this->spreadsheet->getSheet(0);

        $row    = 1;
        $column = 1;

        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('25');

        foreach ($roles as $role) {
            $sheet->setCellValueByColumnAndRow($column, $row, $role->getName());
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column)->setWidth('15');
            $column++;
        }

        foreach ($users as $user) {
            $row++;
            $column = 1;

            $sheet->setCellValueByColumnAndRow($column, $row, $user->getDisplayName());

            foreach ($roles as $role) {
                $column++;
                if (! $user->isInRole($role)) {
                    continue;
                }

                $sheet->setCellValueByColumnAndRow($column, $row, 'X');
            }
        }

        return new ExcelResponse($this->spreadsheet, $filename);
    }

    /**
     * Vyexportuje harmonogram uživatele.
     * @throws Exception
     */
    public function exportUserSchedule(User $user, string $filename) : ExcelResponse
    {
        return $this->exportUsersSchedules(new ArrayCollection([$user]), $filename);
    }

    /**
     * Vyexportuje harmonogramy uživatelů, každý uživatel na zvlástním listu.
     * @param Collection|User[] $users
     * @throws Exception
     * @throws \Exception
     */
    public function exportUsersSchedules(Collection $users, string $filename) : ExcelResponse
    {
        $this->spreadsheet->removeSheetByIndex(0);
        $sheetNumber = 0;

        foreach ($users as $user) {
            $sheet = new Worksheet($this->spreadsheet, self::cleanSheetName($user->getDisplayName()));
            $this->spreadsheet->addSheet($sheet, $sheetNumber++);

            $row    = 1;
            $column = 1;

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.schedule.from'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('15');

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.schedule.to'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('15');

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.schedule.program_name'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('30');

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.schedule.room'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('25');

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.schedule.lectors'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('25');

            foreach ($user->getPrograms() as $program) {
                $row++;
                $column = 1;

                $sheet->setCellValueByColumnAndRow($column++, $row, $program->getStart()->format('j. n. H:i'));
                $sheet->setCellValueByColumnAndRow($column++, $row, $program->getEnd()->format('j. n. H:i'));
                $sheet->setCellValueByColumnAndRow($column++, $row, $program->getBlock()->getName());
                $sheet->setCellValueByColumnAndRow($column++, $row, $program->getRoom() ? $program->getRoom()->getName() : null);
                $sheet->setCellValueByColumnAndRow($column++, $row, $program->getBlock()->getLectorsText());
            }
        }

        return new ExcelResponse($this->spreadsheet, $filename);
    }

    /**
     * Vyexportuje harmonogram místnosti.
     * @throws Exception
     */
    public function exportRoomSchedule(Room $room, string $filename) : ExcelResponse
    {
        return $this->exportRoomsSchedules(new ArrayCollection([$room]), $filename);
    }

    /**
     * Vyexportuje harmonogramy místností.
     * @param Collection|Room[] $rooms
     * @throws Exception
     * @throws \Exception
     */
    public function exportRoomsSchedules(Collection $rooms, string $filename) : ExcelResponse
    {
        $this->spreadsheet->removeSheetByIndex(0);
        $sheetNumber = 0;

        foreach ($rooms as $room) {
            $sheet = new Worksheet($this->spreadsheet, self::cleanSheetName($room->getName()));
            $this->spreadsheet->addSheet($sheet, $sheetNumber++);

            $row    = 1;
            $column = 1;

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.schedule.from'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('15');

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.schedule.to'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('15');

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.schedule.program_name'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('30');

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.schedule.occupancy'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('15');

            foreach ($room->getPrograms() as $program) {
                $row++;
                $column = 1;

                $sheet->setCellValueByColumnAndRow($column++, $row, $program->getStart()->format('j. n. H:i'));
                $sheet->setCellValueByColumnAndRow($column++, $row, $program->getEnd()->format('j. n. H:i'));
                $sheet->setCellValueByColumnAndRow($column++, $row, $program->getBlock()->getName());
                $sheet->setCellValueByColumnAndRow($column++, $row, $room->getCapacity() !== null
                    ? $program->getAttendeesCount() . '/' . $room->getCapacity()
                    : $program->getAttendeesCount());
            }
        }

        return new ExcelResponse($this->spreadsheet, $filename);
    }

    /**
     * @param Collection|User[] $users
     * @throws Exception
     */
    public function exportUsersList(Collection $users, string $filename) : ExcelResponse
    {
        $sheet = $this->spreadsheet->getSheet(0);

        $row    = 1;
        $column = 1;

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.display_name'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('30');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.username'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.roles'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('30');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.subevents'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('30');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.approved'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('10');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.membership'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.age'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('10');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.email'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('30');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.city'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.fee'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('15');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.fee_remaining'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('15');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.variable_symbol'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('25');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.payment_method'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('15');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.payment_date'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.first_application_date'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.attended'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('10');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.not_registared_mandatory_blocks'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('30');

        foreach ($this->customInputRepository->findAllOrderedByPosition() as $customInput) {
            switch ($customInput->getType()) {
                case CustomInput::TEXT:
                    $width = '30';
                    break;

                case CustomInput::CHECKBOX:
                    $width = '15';
                    break;

                case CustomInput::SELECT:
                    $width = '30';
                    break;

                case CustomInput::FILE:
                    continue 2;

                default:
                    throw new \InvalidArgumentException();
            }

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate($customInput->getName()));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth($width);
        }

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.private_note'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('60');

        foreach ($users as $user) {
            $row++;
            $column = 1;

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getDisplayName());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getUsername());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getRolesText());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getSubeventsText());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->isApproved()
                ? $this->translator->translate('common.export.common.yes')
                : $this->translator->translate('common.export.common.no'));

            $sheet->getCellByColumnAndRow($column++, $row)
                ->setValueExplicit($this->userService->getMembershipText($user), DataType::TYPE_STRING);

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getAge());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getEmail());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getCity());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getFee());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getFeeRemaining());

            $sheet->getCellByColumnAndRow($column++, $row)
                ->setValueExplicit($user->getVariableSymbolsText(), DataType::TYPE_STRING);

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getPaymentMethod() ? $this->translator->translate('common.payment.' . $user->getPaymentMethod()) : '');

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getLastPaymentDate() !== null ? $user->getLastPaymentDate()->format(Helpers::DATE_FORMAT) : '');

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getRolesApplicationDate() !== null ? $user->getRolesApplicationDate()->format(Helpers::DATE_FORMAT) : '');

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->isAttended()
                ? $this->translator->translate('common.export.common.yes')
                : $this->translator->translate('common.export.common.no'));

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getNotRegisteredMandatoryBlocksText());

            foreach ($this->customInputRepository->findAllOrderedByPosition() as $customInput) {
                $customInputValue = $user->getCustomInputValue($customInput);

                if ($customInputValue) {
                    switch ($customInputValue->getInput()->getType()) {
                        case CustomInput::TEXT:
                            $value = $customInputValue->getValue();
                            break;

                        case CustomInput::CHECKBOX:
                            $value = $customInputValue->getValue()
                                ? $this->translator->translate('common.export.common.yes')
                                : $this->translator->translate('common.export.common.no');
                            break;

                        case CustomInput::SELECT:
                            $value = $customInputValue->getValueOption();
                            break;

                        case CustomInput::FILE:
                            continue 2;

                        default:
                            throw new \InvalidArgumentException();
                    }
                } else {
                    $value = '';
                }

                $sheet->setCellValueByColumnAndRow($column++, $row, $value);
            }

            $sheet->setCellValueByColumnAndRow($column, $row, $user->getNote());
            $sheet->getStyleByColumnAndRow($column++, $row)->getAlignment()->setWrapText(true);
        }
        return new ExcelResponse($this->spreadsheet, $filename);
    }

    /**
     * @param Collection|User[] $users
     * @throws Exception
     */
    public function exportUsersSubeventsAndCategories(Collection $users, string $filename) : ExcelResponse
    {
        $sheet = $this->spreadsheet->getSheet(0);

        $row    = 1;
        $column = 1;

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.variable_symbol'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.first_name'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.last_name'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

        $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.nickname'));
        $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
        $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
        $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

        foreach ($this->subeventRepository->findAllExplicitOrderedByName() as $subevent) {
            $sheet->setCellValueByColumnAndRow($column, $row, $subevent->getName());
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('10');
        }

        foreach ($this->categoryRepository->findAll() as $category) {
            $sheet->setCellValueByColumnAndRow($column, $row, $category->getName() . ' - ' . $this->translator->translate('common.export.schedule.program_name'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('20');

            $sheet->setCellValueByColumnAndRow($column, $row, $category->getName() . ' - ' . $this->translator->translate('common.export.schedule.room'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('10');
        }

        foreach ($users as $user) {
            $row++;
            $column = 1;

            $sheet->getCellByColumnAndRow($column++, $row)
                ->setValueExplicit($user->getVariableSymbolsText(), DataType::TYPE_STRING);

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getFirstName());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getLastName());

            $sheet->setCellValueByColumnAndRow($column++, $row, $user->getNickname());

            foreach ($this->subeventRepository->findAllExplicitOrderedByName() as $subevent) {
                $sheet->setCellValueByColumnAndRow($column++, $row, $user->hasSubevent($subevent)
                    ? $this->translator->translate('common.export.common.yes')
                    : $this->translator->translate('common.export.common.no'));
            }

            foreach ($this->categoryRepository->findAll() as $category) {
                $blocks = [];
                $rooms  = [];
                foreach ($this->programRepository->findUserRegisteredAndInCategory($user, $category) as $program) {
                    $blocks[] = $program->getBlock()->getName();
                    $rooms[]  = $program->getRoom() ? $program->getRoom()->getName() : '';
                }

                $sheet->setCellValueByColumnAndRow($column++, $row, implode(', ', $blocks));
                $sheet->setCellValueByColumnAndRow($column++, $row, implode(', ', $rooms));
            }
        }
        return new ExcelResponse($this->spreadsheet, $filename);
    }

    /**
     * @param Collection|Block[] $blocks
     * @throws Exception
     */
    public function exportBlocksAttendees(Collection $blocks, string $filename) : ExcelResponse
    {
        $this->spreadsheet->removeSheetByIndex(0);
        $sheetNumber = 0;

        foreach ($blocks as $block) {
            $sheet = new Worksheet($this->spreadsheet, self::cleanSheetName($block->getName()));
            $this->spreadsheet->addSheet($sheet, $sheetNumber++);

            $row    = 1;
            $column = 1;

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.display_name'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('30');

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.email'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('30');

            $sheet->setCellValueByColumnAndRow($column, $row, $this->translator->translate('common.export.user.address'));
            $sheet->getStyleByColumnAndRow($column, $row)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(false);
            $sheet->getColumnDimensionByColumn($column++)->setWidth('40');

            $criteria = Criteria::create()->orderBy(['displayName', 'ASC']);

            foreach ($block->getAttendees()->matching($criteria) as $attendee) {
                $row++;
                $column = 1;

                $sheet->setCellValueByColumnAndRow($column++, $row, $attendee->getDisplayName());
                $sheet->setCellValueByColumnAndRow($column++, $row, $attendee->getEmail());
                $sheet->setCellValueByColumnAndRow($column++, $row, $attendee->getAddress());
            }
        }

        return new ExcelResponse($this->spreadsheet, $filename);
    }

    /**
     * Odstraní z názvu listu zakázané znaky a zkrátí jej na 31 znaků.
     */
    private static function cleanSheetName(string $name) : string
    {
        $name = preg_replace('[\\/\*\[\]:?]', '', $name);
        return Helpers::truncate($name, 28);
    }
}
