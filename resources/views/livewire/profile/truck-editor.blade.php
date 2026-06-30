<div class="truck-editor">
    {{--
        The actual form is a nested anonymous Blade component. It compiles inline
        here, so its `wire:model` / `wire:click` directives bind straight to this
        TruckEditor component; we pass the repeatable/collection data it needs to
        render rows as props.
    --}}
    <x-truck-form
        :truck-id="$truckId"
        :menu-items="$menuItems"
        :images="$images"
    />
</div>
