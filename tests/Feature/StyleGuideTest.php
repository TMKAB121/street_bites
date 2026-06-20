<?php

/*
 | A real example: the living style guide route should render and show the
 | "Urban Vibrant" design-system heading. Chained expectations read top-down.
 */

it('renders the living style guide', function (): void {
    $this->get('/styleguide')
        ->assertOk()
        ->assertSee('Urban Vibrant')
        ->assertSee('Food truck card');
});
