<x-filament-panels::page>
    <form wire:submit="send">
        {{ $this->form }}
        
        <div class="flex justify-end mt-4">
            <x-filament::button type="submit">
                Start Sending
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

