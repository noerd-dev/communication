<?php

use Livewire\Component;
use Noerd\Communication\Models\Communication;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = Communication::class;
    public $detailComponent = 'communication::communication-detail';

    public function mount(): void
    {
        $this->mountList();
        $this->setDefaultSort('sent_at', false);
    }

    public function rendering()
    {
        if ((int) request()->communicationId) {
            $this->listAction(request()->communicationId);
        }
    }
};
?>

<x-noerd::page>
    <x-noerd::list/>
</x-noerd::page>
