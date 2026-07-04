<?php

/*
 | The public "About us" page (linked from the hamburger menu) should render
 | for guests with the mission, the developer intro, and the follow-along
 | cards. No DB rows are involved — it's a static view on the shell chrome.
 */

it('renders the about page', function (): void {
    $this->get('/about')
        ->assertOk()
        ->assertSee('About Street Bites')
        ->assertSee('The mission behind Street Bites')
        ->assertSee('Tony Sayge')
        ->assertSee('Follow the journey');
});
