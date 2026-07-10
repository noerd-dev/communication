<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::livewire('communications', 'communication::communications-list')->name('communications');
    Route::livewire('communication/{modelId}', 'communication::communication-detail')->name('communication.detail');
    Route::livewire('communication-settings', 'communication::communication-settings-detail')->name('communication.settings');

    Route::redirect('/sent-mails', '/communications', 301);
    Route::get('/sent-mail/{mail}', fn ($mail) => redirect("/communication/{$mail}", 301));
    Route::redirect('/marketing-settings', '/communication-settings', 301);
});
