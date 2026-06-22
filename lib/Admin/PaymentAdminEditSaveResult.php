<?php

namespace Zr\PaidAccess\Admin;

class PaymentAdminEditSaveResult
{
    /** @var bool */
    public $success = false;

    /** @var int */
    public $paymentId = 0;

    /** @var bool */
    public $isEditMode = false;

    /** @var array<string, mixed> */
    public $formValues = [];

    /** @var string */
    public $errorMessage = '';

    /** @var string */
    public $redirectUrl = '';

    /** @var int */
    public $redirectHttpStatus = 302;

    /** @var bool */
    public $showSuccessMessage = false;

    public function hasRedirect(): bool
    {
        return $this->redirectUrl !== '';
    }
}
