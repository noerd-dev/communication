<?php

use Livewire\Component;
use Noerd\Communication\Models\Communication;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public $detailModel = Communication::class;

    public ?string $detailPrimary = 'communicationId';
};
?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Communication Details') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
        <x-slot:tab1>
            @if(! empty($detailData['body']))
                <div class="mt-4">
                    <h3 class="text-sm font-semibold mb-2">{{ __('Body') }}</h3>
                    {{--
                        The logged body is the HTML that was actually sent, and
                        parts of it came from whoever filled the form or booking
                        widget the mail was generated from. Rendering it into
                        this page would execute the sender's markup in the
                        reader's authenticated session, so it goes into a fully
                        sandboxed iframe with an escaped attribute — the same
                        treatment the core email preview gives template output.
                    --}}
                    <div class="border rounded overflow-hidden bg-white">
                        <iframe
                            srcdoc="{{ $detailData['body'] }}"
                            class="w-full h-96 bg-white"
                            sandbox=""
                            title="{{ __('Body') }}">
                        </iframe>
                    </div>
                </div>
            @endif
        </x-slot:tab1>
    </x-noerd::tab-content>
</x-noerd::page>
