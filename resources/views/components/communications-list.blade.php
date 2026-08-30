<?php

use Livewire\Component;
use Noerd\Communication\Models\Communication;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = Communication::class;
    public ?string $detailRoute = 'communication.detail';

    public ?string $modelType = null;
    public ?int $modelId = null;

    public function listData(): array
    {
        $rows = $this->listQuery($this->listModel)
            ->when($this->modelType && $this->modelId, fn ($query) => $query
                ->where('model_type', $this->modelType)
                ->where('model_id', $this->modelId))
            ->paginate($this->perPage);

        return $this->buildList($rows);
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
