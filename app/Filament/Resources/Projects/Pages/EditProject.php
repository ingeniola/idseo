<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Widgets\ProjectVisibilityChartWidget;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Enums\ContentTabPosition;

/**
 * Por defecto, EditRecord apila el formulario de edición ARRIBA de
 * las pestañas de RelationManagers (Keywords, Reports, etc.) — quien
 * entra a un proyecto aterriza en un formulario de edición, no en la
 * pantalla de trabajo (estilo SEMrush: entrar a un proyecto muestra
 * sus datos, no un form). hasCombinedRelationManagerTabsWithContent()
 * fusiona el form en UNA pestaña más ("Configuración del proyecto"),
 * al final de la barra en vez de encima — así la primera pestaña
 * (Keywords) es lo que se ve al entrar, y "editar" es una pestaña más
 * que se visita a propósito, no la pantalla de aterrizaje.
 */
class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabPosition(): ?ContentTabPosition
    {
        return ContentTabPosition::After;
    }

    public function getContentTabLabel(): ?string
    {
        return __('projects.edit_tab_label');
    }

    /**
     * En modo combinado, HasRelationManagers::renderingHasRelationManagers()
     * (que corre en CADA render, no solo al montar) deja
     * $activeRelationManager en null a propósito — porque null es
     * también lo que el botón de "Configuración del proyecto" manda
     * (wire:click="$set('activeRelationManager', null)"), ya que
     * Tabs::toEmbeddedHtmlForLivewireProperty() hace strval(null) ===
     * '', la clave sintética de ese tab. No se puede distinguir
     * "todavía no se eligió nada" de "el usuario clickeó
     * Configuración" mirando solo el valor — así que el default va
     * en mount(), que corre una sola vez, y NO en el hook de
     * renderingHasRelationManagers(): sobreescribirlo ahí resetearía
     * el tab a Keywords cada vez que alguien fuera a Configuración.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->activeRelationManager === null) {
            // array_key_first() devuelve int (los RelationManagers
            // están en un array secuencial 0-indexado); sin el cast,
            // la asignación a esta propiedad tipada ?string revienta
            // con un TypeError bajo strict_types.
            $this->activeRelationManager = (string) array_key_first($this->getCachedRelationManagers());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ProjectVisibilityChartWidget::class,
        ];
    }
}
