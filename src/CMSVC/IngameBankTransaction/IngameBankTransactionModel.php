<?php

declare(strict_types=1);

namespace App\CMSVC\IngameBankTransaction;

use Fraym\BaseObject\BaseModel;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

class IngameBankTransactionModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Text(
        maxChar: 255,
    )]
    public Item\Text $name;

    #[Attribute\Number(
        context: [
            ':list',
            ':view',
        ],
    )]
    public Item\Number $from_project_application_id;

    #[Attribute\Select(
        values: 'getBankCurrencyIdValues',
        locked: 'getFromBankCurrencyIdLocked',
        obligatory: true,
    )]
    public Item\Select $from_bank_currency_id;

    #[Attribute\Number(
        round: true,
        obligatory: true,
    )]
    public Item\Number $amount_from;

    #[Attribute\Number(
        obligatory: true,
    )]
    public Item\Number $to_project_application_id;

    #[Attribute\Select(
        values: 'getBankCurrencyIdValues',
    )]
    public Item\Select $bank_currency_id;

    #[Attribute\Number(
        context: [
            ':list',
            ':view',
        ],
    )]
    public Item\Number $amount;

    #[Attribute\Timestamp(
        showInObjects: true,
        context: [
            ':list',
            ':view',
            ':create',
        ],
    )]
    #[Attribute\OnCreate(callback: 'getTime')]
    public Item\Timestamp $created_at;

    #[Attribute\Hidden(
        context: [
            ':list',
            ':view',
            ':create',
        ],
    )]
    #[Attribute\OnCreate(callback: 'getProjectId')]
    public Item\Hidden $project_id;

    public function getProjectId(): ?int
    {
        /** @var IngameBankTransactionService */
        $ingameBankTransactionService = $this->getCMSVC()->getService();

        return $ingameBankTransactionService->getActivatedProjectId();
    }
}
