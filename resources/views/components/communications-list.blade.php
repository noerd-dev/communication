<?php

use Livewire\Component;
use Noerd\Marketing\Models\Communication;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public function mount(): void
    {
        $this->mountList();
        $this->setDefaultSort('sent_at', false);
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'marketing::communication-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with()
    {
        $rows = $this->listQuery(Communication::class)->paginate($this->perPage);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        if ((int) request()->communicationId) {
            $this->listAction(request()->communicationId);
        }
    }
};
?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list/>
</x-noerd::page>
