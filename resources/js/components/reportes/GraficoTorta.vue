<script setup lang="ts">
import { computed } from 'vue';
import { formatearMonto, PALETA } from '@/lib/formatoReporte';

const props = defineProps<{
    etiquetas: string[];
    valores: number[];
    moneda?: boolean;
}>();

const RADIO = 90;
const CENTRO = 110;

const total = computed(() =>
    props.valores.reduce((suma, valor) => suma + Math.max(0, valor), 0),
);

const sectores = computed(() => {
    let acumulado = 0;

    return props.valores.map((valor, indice) => {
        const parte = total.value > 0 ? Math.max(0, valor) / total.value : 0;
        const inicio = acumulado * 2 * Math.PI - Math.PI / 2;
        acumulado += parte;
        const fin = acumulado * 2 * Math.PI - Math.PI / 2;
        const grande = parte > 0.5 ? 1 : 0;
        const punto = (angulo: number) =>
            `${CENTRO + RADIO * Math.cos(angulo)} ${CENTRO + RADIO * Math.sin(angulo)}`;

        return {
            etiqueta: props.etiquetas[indice] ?? '',
            valor,
            porcentaje: parte * 100,
            color: PALETA[indice % PALETA.length],
            completo: parte >= 0.9999,
            ruta: `M ${CENTRO} ${CENTRO} L ${punto(inicio)} A ${RADIO} ${RADIO} 0 ${grande} 1 ${punto(fin)} Z`,
        };
    });
});

const mostrar = (valor: number): string =>
    props.moneda ? formatearMonto(valor) : String(valor);

const porcentaje = (valor: number): string =>
    `${valor.toLocaleString('es-BO', { maximumFractionDigits: 1 })} %`;
</script>

<template>
    <figure class="cc-torta">
        <svg
            viewBox="0 0 220 220"
            role="img"
            aria-label="Gráfico de torta del reporte"
        >
            <template v-for="sector in sectores" :key="sector.etiqueta">
                <circle
                    v-if="sector.completo"
                    :cx="CENTRO"
                    :cy="CENTRO"
                    :r="RADIO"
                    :fill="sector.color"
                />
                <path
                    v-else-if="sector.porcentaje > 0"
                    :d="sector.ruta"
                    :fill="sector.color"
                    stroke="var(--cc-surface, #fff)"
                    stroke-width="1.5"
                >
                    <title>
                        {{ sector.etiqueta }}: {{ mostrar(sector.valor) }}
                    </title>
                </path>
            </template>
        </svg>
        <figcaption>
            <ul class="cc-torta__leyenda">
                <li v-for="sector in sectores" :key="sector.etiqueta">
                    <i :style="{ background: sector.color }" />
                    <span>{{ sector.etiqueta }}</span>
                    <b>{{ mostrar(sector.valor) }}</b>
                    <small>{{ porcentaje(sector.porcentaje) }}</small>
                </li>
            </ul>
        </figcaption>
    </figure>
</template>

<style scoped>
.cc-torta {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    align-items: center;
    margin: 0;
}

.cc-torta svg {
    flex: 0 0 220px;
    width: 220px;
    height: 220px;
}

.cc-torta figcaption {
    flex: 1;
    min-width: 220px;
}

.cc-torta__leyenda {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: 13px;
}

.cc-torta__leyenda li {
    display: grid;
    grid-template-columns: 12px 1fr auto auto;
    gap: 10px;
    align-items: center;
}

.cc-torta__leyenda i {
    width: 10px;
    height: 10px;
    border-radius: 2px;
}

.cc-torta__leyenda b {
    font-variant-numeric: tabular-nums;
}

.cc-torta__leyenda small {
    min-width: 52px;
    color: var(--cc-muted);
    text-align: right;
    font-variant-numeric: tabular-nums;
}
</style>
