<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="mt-4 text-right">
            <x-filament::button type="submit">
                Save Configuration
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

