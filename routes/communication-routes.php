<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['noerd']], function (): void {
    Route::livewire('communications', 'communication::communications-list')->name('communications');
    Route::livewire('communication/{modelId}', 'communication::communication-detail')->name('communication.detail');
    Route::livewire('mail-senders', 'communication::mail-senders-list')->name('mail-senders');
    Route::livewire('mail-sender/{modelId}', 'communication::mail-sender-detail')->name('mail-sender.detail');

    Route::redirect('/sent-mails', '/communications', 301);
    Route::get('/sent-mail/{mail}', fn($mail) => redirect("/communication/{$mail}", 301));
    // The settings page is gone: the sender accounts replaced its only field.
    Route::redirect('/marketing-settings', '/mail-senders', 301);
    Route::redirect('/communication-settings', '/mail-senders', 301);
});
