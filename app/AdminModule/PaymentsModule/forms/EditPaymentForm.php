<?php

declare(strict_types=1);

namespace App\AdminModule\PaymentsModule\Forms;

use App\AdminModule\Forms\BaseForm;
use App\Model\Payment\Payment;
use App\Model\Payment\PaymentRepository;
use App\Model\User\ApplicationRepository;
use App\Model\User\UserRepository;
use App\Services\ApplicationService;
use Nette;
use Nette\Application\UI\Form;

/**
 * Formulář pro úpravu platby.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class EditPaymentForm
{
    use Nette\SmartObject;

    /**
     * Upravovaná platba.
     * @var Payment
     */
    private $payment;

    /** @var BaseForm */
    private $baseFormFactory;

    /** @var PaymentRepository */
    private $paymentRepository;

    /** @var ApplicationRepository */
    private $applicationRepository;

    /** @var UserRepository */
    private $userRepository;

    /** @var ApplicationService */
    private $applicationService;


    public function __construct(
        BaseForm $baseFormFactory,
        PaymentRepository $paymentRepository,
        ApplicationRepository $applicationRepository,
        UserRepository $userRepository,
        ApplicationService $applicationService
    ) {
        $this->baseFormFactory       = $baseFormFactory;
        $this->paymentRepository     = $paymentRepository;
        $this->applicationRepository = $applicationRepository;
        $this->userRepository        = $userRepository;
        $this->applicationService    = $applicationService;
    }

    /**
     * Vytvoří formulář.
     */
    public function create(int $id) : Form
    {
        $this->payment = $this->paymentRepository->findById($id);

        $form = $this->baseFormFactory->create();

        $form->addHidden('id');

        $inputDate = $form->addDatePicker('date', 'admin.payments.payments.date');

        $inputAmount = $form->addInteger('amount', 'admin.payments.payments.amount');

        $inputVariableSymbol = $form->addText('variableSymbol', 'admin.payments.payments.variable_symbol');

        $inputPairedApplication = $form->addMultiSelect('pairedApplications', 'admin.payments.payments.paired_applications', $this->applicationRepository->getApplicationsVariableSymbolsOptions())
            ->setAttribute('class', 'datagrid-multiselect')
            ->setAttribute('data-live-search', 'true');

        $form->addSubmit('submit', 'admin.common.save');

        $form->addSubmit('cancel', 'admin.common.cancel')
            ->setValidationScope([])
            ->setAttribute('class', 'btn btn-warning');

        if ($this->payment->getTransactionId() === null) {
            $inputDate
                ->addRule(Form::FILLED, 'admin.payments.payments.date_empty');

            $inputAmount
                ->addRule(Form::FILLED, 'admin.payments.payments.amount_empty')
                ->addRule(Form::MIN, 'admin.payments.payments.amount_low', 1);

            $inputVariableSymbol
                ->addRule(Form::FILLED, 'admin.payments.payments.variable_symbol_empty');
        } else {
            $inputDate->setDisabled();
            $inputAmount->setDisabled();
            $inputVariableSymbol->setDisabled();
        }

        $pairedValidApplications = $this->payment->getPairedValidApplications();

        $inputPairedApplication->setItems(
            $this->applicationRepository->getWaitingForPaymentOrPairedApplicationsVariableSymbolsOptions($pairedValidApplications)
        );

        $form->setDefaults([
            'id' => $id,
            'date' => $this->payment->getDate(),
            'amount' => $this->payment->getAmount(),
            'variableSymbol' => $this->payment->getVariableSymbol(),
            'pairedApplications' => $this->applicationRepository->findApplicationsIds($pairedValidApplications),
        ]);

        $form->onSuccess[] = [$this, 'processForm'];

        return $form;
    }

    /**
     * Zpracuje formulář.
     * @throws \Throwable
     */
    public function processForm(Form $form, \stdClass $values) : void
    {
        if (! $form['cancel']->isSubmittedBy()) {
            $loggedUser = $this->userRepository->findById($form->getPresenter()->user->id);

            $pairedApplications = $this->applicationRepository->findApplicationsByIds($values['pairedApplications']);

            $this->applicationService->updatePayment($this->payment, $values['date'], $values['amount'], $values['variableSymbol'], $pairedApplications, $loggedUser);
        }
    }
}
