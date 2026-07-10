<?php

use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Communication\Models\Communication;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public const DETAIL_CLASS = Communication::class;

    #[Url(as: 'communicationId', keep: false, except: '')]
    public $modelId = null;

    public function mount(): void
    {
        $this->initDetail();

        if ($this->modelId) {
            $communication = Communication::find($this->modelId);
            if ($communication) {
                $this->detailData = $communication->toArray();
            }
        }
    }
};
?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Communication Details') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
        <x-slot:tab1>
            @if(! empty($detailData['body']))
                <div class="mt-4">
                    <h3 class="text-sm font-semibold mb-2">{{ __('Body') }}</h3>
                    <div class="border rounded p-4 bg-gray-50 max-h-96 overflow-auto prose prose-sm max-w-none">
                        {!! $detailData['body'] !!}
                    </div>
                </div>
            @endif
        </x-slot:tab1>
    </x-noerd::tab-content>
</x-noerd::page>
