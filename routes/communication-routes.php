<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['noerd']], function (): void {
    Route::livewire('communications', 'communication::communications-list')->name('communications');
    Route::livewire('communication/{modelId}', 'communication::communication-detail')->name('communication.detail');
    Route::livewire('mail-senders', 'communication::mail-senders-list')->name('mail-senders');
    Route::livewire('mail-sender/{modelId}', 'communication::mail-sender-detail')->name('mail-sender.detail');
});
