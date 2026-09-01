<?php

use Livewire\Component;
use Noerd\Communication\Models\MailSender;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = MailSender::class;

    public ?string $detailRoute = 'mail-sender.detail';

    public $detailComponent = 'communication::mail-sender-detail';
};
?>

<x-noerd::page>
    <x-noerd::list/>
</x-noerd::page>
