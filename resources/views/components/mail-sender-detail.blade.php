<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Noerd\Communication\Models\MailSender;
use Noerd\Communication\Services\TenantSmtpResolver;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public $detailModel = MailSender::class;

    public ?string $detailPrimary = 'mailSenderId';

    public ?string $testEmailMessage = null;

    public ?string $testEmailError = null;

    /**
     * YAML action `sendTestEmail`: sends a test mail THROUGH THIS ACCOUNT to the logged-in
     * user, so credentials are verified per sender rather than per tenant. Rate limited to
     * once per minute and per sender. The lookup runs through the tenant global scope, so a
     * foreign sender id resolves to nothing.
     */
    public function sendTestEmail(): void
    {
        $this->testEmailMessage = null;
        $this->testEmailError = null;

        $sender = $this->modelId ? MailSender::find($this->modelId) : null;
        $user = auth()->user();

        if (! $sender) {
            $this->testEmailError = __('Save the sender before sending a test email.');

            return;
        }

        if (! $user || ! $user->email) {
            $this->testEmailError = __('No email address available for the current user.');

            return;
        }

        $cacheKey = 'communication-test-email-cooldown:' . $sender->getKey() . ':' . $user->getKey();

        if (Cache::has($cacheKey)) {
            $this->testEmailError = __('Send test email (only possible once per minute)');

            return;
        }

        $fromEmail = $sender->from_email ?: config('mail.from.address');

        try {
            app(TenantSmtpResolver::class)->resolveForSender($sender)->raw(
                __('This is a test email from the Communication module.'),
                function ($message) use ($user, $fromEmail): void {
                    $message->to($user->email)
                        ->from($fromEmail)
                        ->subject(__('Communication Test Email'));
                },
            );
        } catch (\Throwable $e) {
            $this->testEmailError = $e->getMessage();

            return;
        }

        Cache::put($cacheKey, true, 60);
        $this->testEmailMessage = __('Test email sent to :email', ['email' => $user->email]);
    }
};
?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Sender') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
        @if($testEmailMessage)
            <p class="mt-4 text-sm text-green-700">{{ $testEmailMessage }}</p>
        @endif
        @if($testEmailError)
            <p class="mt-4 text-sm text-red-700">{{ $testEmailError }}</p>
        @endif
    </x-noerd::tab-content>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
