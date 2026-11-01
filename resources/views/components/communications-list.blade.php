<?php

use Livewire\Component;
use Noerd\Communication\Models\Communication;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = Communication::class;
    public ?string $detailRoute = 'communication.detail';

    public $detailComponent = 'communication::communication-detail';

    /** Narrows the log to the mails of one source record (embedded in its detail). */
    public ?string $modelType = null;

    public ?int $modelId = null;

    protected array $listScopes = ['model_type' => 'modelType', 'model_id' => 'modelId'];
};
?>

<x-noerd::page>
    <x-noerd::list/>
</x-noerd::page>
