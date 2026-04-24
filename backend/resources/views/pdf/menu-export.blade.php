<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Menú — {{ $restaurant->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a2e; font-size: 11px; line-height: 1.55; background: #ffffff; }
        .cover { background: #1a1a2e; color: #ffffff; padding: 28px 32px 24px; margin-bottom: 24px; }
        .cover-brand { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; color: #f59e0b; margin-bottom: 8px; }
        .cover-name { font-size: 26px; font-weight: bold; margin-bottom: 14px; color: #ffffff; }
        .cover-meta-row { font-size: 10px; color: #9ca3af; }
        .cover-meta-row strong { color: #e5e7eb; }
        .cover-divider { display: inline-block; margin: 0 8px; color: #4b5563; }
        .catalog { margin-bottom: 28px; page-break-inside: avoid; }
        .catalog-header { background: #f59e0b; padding: 0; margin-bottom: 4px; }
        .catalog-header-inner { padding: 9px 14px; }
        .catalog-title { font-size: 14px; font-weight: bold; color: #1a1a2e; }
        .catalog-description { font-size: 10px; color: #374151; padding: 6px 14px 10px; background: #fffbeb; border-left: 3px solid #f59e0b; margin-bottom: 12px; }
        .section { margin-bottom: 16px; }
        .section-header { border-bottom: 1.5px solid #1a1a2e; padding-bottom: 4px; margin-bottom: 8px; }
        .section-title { font-size: 12px; font-weight: bold; color: #1a1a2e; text-transform: uppercase; letter-spacing: 0.8px; }
        .section-accent { display: inline-block; width: 22px; height: 3px; background: #f59e0b; margin-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 10.5px; }
        thead tr { background: #1a1a2e; }
        thead th { color: #f9fafb; padding: 7px 9px; text-align: left; font-size: 9.5px; letter-spacing: 0.6px; text-transform: uppercase; border: none; }
        tbody tr { border-bottom: 1px solid #e5e7eb; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tbody tr:nth-child(odd) { background: #ffffff; }
        tbody td { padding: 7px 9px; vertical-align: top; color: #374151; }
        .col-name { font-weight: bold; color: #1a1a2e; }
        .col-price { font-weight: bold; color: #065f46; white-space: nowrap; }
        .badge { display: inline-block; font-size: 8.5px; font-weight: bold; padding: 1px 5px; margin-left: 5px; }
        .badge-new { background: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
        .badge-hidden { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-active { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .muted { color: #9ca3af; font-style: italic; }
        .empty-row td { text-align: center; padding: 12px; color: #9ca3af; font-style: italic; background: #f9fafb; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
        .footer-left { float: left; }
        .footer-right { float: right; }
        .footer-clear { clear: both; }
        .footer-logo { font-weight: bold; color: #f59e0b; font-size: 10px; }
    </style>
</head>
<body>
    <div class="cover">
        <div class="cover-brand">Scan2Order &mdash; Carta Digital</div>
        <div class="cover-name">{{ $restaurant->name }}</div>
        <div class="cover-meta-row">
            @if($restaurant->address)
                <strong>📍</strong> {{ $restaurant->address }}
                <span class="cover-divider">|</span>
            @endif
            @if($restaurant->phone)
                <strong>📞</strong> {{ $restaurant->phone }}
                <span class="cover-divider">|</span>
            @endif
            <strong>Generado:</strong> {{ $generatedAt->format('d/m/Y \a \l\a\s H:i') }}
        </div>
    </div>

    @forelse($restaurant->catalogs as $catalog)
        <div class="catalog">
            <div class="catalog-header">
                <div class="catalog-header-inner">
                    <span class="catalog-title">{{ strtoupper($catalog->name) }}</span>
                </div>
            </div>
            @if($catalog->description)
                <div class="catalog-description">{{ $catalog->description }}</div>
            @endif
            @forelse($catalog->sections as $section)
                <div class="section">
                    <div class="section-header">
                        <div class="section-accent"></div>
                        <span class="section-title">{{ $section->name }}</span>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 26%;">Producto</th>
                                <th style="width: 36%;">Descripción</th>
                                <th style="width: 10%;">Precio</th>
                                <th style="width: 13%;">Estado</th>
                                <th style="width: 15%;">Alérgenos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($section->products as $product)
                                <tr>
                                    <td>
                                        <span class="col-name">{{ $product->name }}</span>
                                        @if($product->is_new)
                                            <span class="badge badge-new">NUEVO</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($product->description)
                                            {{ $product->description }}
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td class="col-price">
                                        {{ number_format((float) $product->price, 2, ',', '.') }} €
                                    </td>
                                    <td>
                                        @if($product->active)
                                            <span class="badge badge-active">Visible</span>
                                        @else
                                            <span class="badge badge-hidden">Oculto</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(is_array($product->allergens) && count($product->allergens) > 0)
                                            {{ implode(', ', $product->allergens) }}
                                        @else
                                            <span class="muted">Ninguno</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="5">No hay productos en esta sección.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="muted" style="padding: 8px 0;">Este catálogo no tiene secciones.</p>
            @endforelse
        </div>
    @empty
        <p class="muted" style="padding: 12px 0; text-align: center;">
            Este restaurante no tiene catálogos para exportar.
        </p>
    @endforelse

    <div class="footer">
        <span class="footer-left">
            <span class="footer-logo">Scan2Order</span>
            &nbsp;&mdash;&nbsp;Exportación administrativa de carta
        </span>
        <span class="footer-right">
            {{ $restaurant->name }} &nbsp;·&nbsp; {{ $generatedAt->format('d/m/Y') }}
        </span>
        <div class="footer-clear"></div>
    </div>
</body>
</html>
