<?php
namespace Cairn\UserCyclosBundle\Entity;

//manage Cyclos configuration file                                             
use Cyclos;

class BankingManager
{
    private $paymentService;
    private $recurringPaymentService;
    private $scheduledPaymentService;
    private $accountService;

    public function __construct()
    {
        $this->paymentService = new Cyclos\PaymentService();
        $this->recurringPaymentService = new Cyclos\RecurringPaymentService();
        $this->scheduledPaymentService = new Cyclos\ScheduledPaymentService();
        $this->accountService = new Cyclos\AccountService();
    }

    public function hydrateParameters($paymentData,$amount,$description,$transferType)
    {
        $parameters = new \stdClass();
        $parameters->from = $paymentData->from;
        $parameters->to = $paymentData->to;
        $parameters->description = $description; 
        foreach($paymentData->paymentTypes as $paymentType){//$data->paymentTypes[0] may not be enough : several paymentTypes matching a given tra                                                       nsfer type
            if($paymentType->internalName == $transferType->internalName){
                $parameters->type = $paymentType;
            }
        }
        $parameters->amount = $amount;
        return $parameters;
    }

    /**
     *
     *@TODO : deal with the delay according to the provided date
     */
    public function makeSinglePreview($paymentData,$amount,$description,$transferType, $date)
    {
        $parameters = $this->hydrateParameters($paymentData,$amount,$description,$transferType);

        if($date->format('Y-m-d') == date('Y-m-d')){
            return $this->paymentService->preview($parameters);
        }

        $parameters->firstInstallmentDate = $date->format('Y-m-d');
        $parameters->installmentsCount = 1;
        return $this->scheduledPaymentService->preview($parameters);
    }

    public function makeRecurringPreview($paymentData,$amount,$description,$transferType,$timeData)
    {
        $parameters = $this->hydrateParameters($paymentData,$amount,$description,$transferType);

        $parameters->firstOccurrenceDate = $timeData->firstOccurrenceDate->format('Y-m-d');

        if($parameters->firstOccurrenceDate == date('Y-m-d')){
            $parameters->firstOccurrenceIsNow = true;
        }

        $parameters->occurrenceInterval = new \stdClass();
        $parameters->occurrenceInterval->field = 'MONTHS';
        $parameters->occurrenceInterval->amount = $timeData->periodicity;
        
        $amount = $parameters->occurrenceInterval->amount;

        $interval = $timeData->firstOccurrenceDate->diff($timeData->lastOccurrenceDate);
        $diff = $interval->m;
        if($interval->invert == 0){
            $nbOccurrences = intdiv($diff, $parameters->occurrenceInterval->amount) + 1;
            if($nbOccurrences <= 1){
                throw new \Exception('La période entre les 2 dates n\'est pas assez importante : ' .$nbOccurrences. ' occurrence calculée.');
            }
        }
        else{//managed by controller but backup 
            throw new \Exception('La date de dernière échéance ne peut être antérieure à la date de première échéance.');
        }
         $parameters->occurrencesCount = $nbOccurrences;

//        //for testing, monthly payments are not very appropriated, so settle daily payments in commented lines
        $parameters->firstOccurrenceDateIsNow = true;
        $parameters->occurrenceInterval = new \stdClass();
        $parameters->occurrenceInterval->field = 'MINUTES';
        $parameters->occurrenceInterval->amount = 1;
         $parameters->occurrencesCount = 3;
    
        return $this->recurringPaymentService->preview($parameters);

    }


    public function makePayment($paymentDTO)
    {
        if(property_exists($paymentDTO,'installmentsCount')){
            return $this->scheduledPaymentService->perform($paymentDTO);
        }
        return $this->paymentService->perform($paymentDTO);
    }

    public function makeRecurringPayment($recurringPaymentDTO)
    {
        return $this->recurringPaymentService->perform($recurringPaymentDTO);
    }

    public function cancelRecurringPayment($recurringPaymentDTO)
    {
        $this->recurringPaymentService->cancel($recurringPaymentDTO);
    }

    public function processOccurrence($id)
    {
        return $this->recurringPaymentService->processFailure($id);
    }

    public function changeInstallmentStatus($DTO,$status)
    {
        $res = new \stdClass();
        if($status == 'block'){
            $this->scheduledPaymentService->block($DTO);
            $res->validStatus = true;
        }elseif($status == 'open'){
             $this->scheduledPaymentService->unblock($DTO);
            $res->validStatus = true;

        }elseif($status == 'cancel'){
             $this->scheduledPaymentService->cancel($DTO);
             $res->validStatus = true;

        }else{
            $res->validStatus = false;
            $res->message = 'Le statut du paiement en attente indiqué ne correspond à aucune action possible.';
        }

        return $res;
    }

    public function editLimitAccount($accountLimitDTO)
    {
        $this->accountService->setBalanceLimit($accountLimitDTO);
    }       


}
