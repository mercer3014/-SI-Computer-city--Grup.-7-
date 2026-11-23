<script setup lang="ts">
import { computed } from 'vue';
import {
    abreviar,
    formatearMonto,
    PALETA,
    techoRedondo,
} from '@/lib/formatoReporte';
import type { SerieGrafico } from '@/lib/formatoReporte';

const props = withDefaults(
    defineProps<{
        etiquetas: string[];
        series: SerieGrafico[];
        moneda?: boolean;
    }>(),
    { moneda: false },
);

const ANCHO = 640;
const ALTO = 300;
const MARGEN = { arriba: 16, derecha: 16, abajo: 64, izquierda: 56 };
const AREA_ANCHO = ANCHO - MARGEN.izquierda - MARGEN.derecha;
const AREA_ALTO = ALTO - MARGEN.arriba - MARGEN.abajo;

const todos = computed(() => props.series.flatMap((s) => s.valores));
const maximo = computed(() => techoRedondo(Math.max(0, ...todos.value)));
const minimo = computed(() => {
    const menor = Math.min(0, ...todos.value);

    return menor < 0 ? -techoRedondo(-menor) : 0;
});

const escala = (valor: number): number =>
    MARGEN.arriba +
    AREA_ALTO * (1 - (valor - minimo.value) / (maximo.value - minimo.value));

const guias = computed(() =>
    Array.from({ length: 5 }, (_, i) => {
        const valor = minimo.value + ((maximo.value - minimo.value) * i) / 4;

        return { valor, y: escala(valor) };
    }),
);

const anchoGrupo = computed(
    () => AREA_ANCHO / Math.max(1, props.etiquetas.length),
);
const anchoBarra = computed(
    () => (anchoGrupo.value * 0.72) / Math.max(1, props.series.length),
);

const barras = computed(() =>
    props.series.flatMap((serie, indiceSerie) =>
        serie.valores.map((valor, indice) => {
            const x =
                MARGEN.izquierda +
                indice * anchoGrupo.value +
                anchoGrupo.value * 0.14 +
                indiceSerie * anchoBarra.value;
            const yValor = escala(valor);
            const yCero = escala(0);

            return {
                clave: `${indiceSerie}-${indice}`,
                x,
                y: Math.min(yValor, yCero),
                alto: Math.max(1, Math.abs(yCero - yValor)),
                color: PALETA[indiceSerie % PALETA.length],
                titulo: `${serie.nombre} · ${props.etiquetas[indice] ?? ''}: ${
                    props.moneda ? formatearMonto(valor) : valor
                }`,
            };
        }),
    ),
);

const recortar = (texto: string): string =>
    texto.length > 16 ? `${texto.slice(0, 15)}…` : texto;

const rotar = computed(() => props.etiquetas.length > 5);
</script>

<template>
    <figure class="cc-grafico">
        <svg
            :viewBox="`0 0 ${ANCHO} ${ALTO}`"
            role="img"
            aria-label="Gráfico de barras del reporte"
        >
            <g class="cc-grafico__guias">
                <g v-for="guia in guias" :key="guia.valor">
                    <line
                        :x1="MARGEN.izquierda"
                        :x2="ANCHO - MARGEN.derecha"
                        :y1="guia.y"
                        :y2="guia.y"
                    />
                    <text
                        :x="MARGEN.izquierda - 8"
                        :y="guia.y + 4"
                        text-anchor="end"
                    >
                        {{ abreviar(guia.valor) }}
                    </text>
                </g>
            </g>
            <rect
                v-for="barra in barras"
                :key="barra.clave"
                :x="barra.x"
                :y="barra.y"
                :width="anchoBarra - 2"
                :height="barra.alto"
                :fill="barra.color"
                rx="2"
            >
                <title>{{ barra.titulo }}</title>
            </rect>
            <g class="cc-grafico__etiquetas">
                <text
                    v-for="(etiqueta, indice) in etiquetas"
                    :key="indice"
                    :x="MARGEN.izquierda + indice * anchoGrupo + anchoGrupo / 2"
                    :y="ALTO - MARGEN.abajo + 18"
                    :text-anchor="rotar ? 'end' : 'middle'"
                    :transform="
                        rotar
                            ? `rotate(-35 ${MARGEN.izquierda + indice * anchoGrupo + anchoGrupo / 2} ${ALTO - MARGEN.abajo + 18})`
                            : undefined
                    "
                >
                    {{ recortar(etiqueta) }}
                </text>
            </g>
        </svg>
        <figcaption v-if="series.length > 1" class="cc-grafico__leyenda">
            <span v-for="(serie, indice) in series" :key="serie.nombre">
                <i :style="{ background: PALETA[indice % PALETA.length] }" />
                {{ serie.nombre }}
            </span>
        </figcaption>
    </figure>
</template>

<style scoped>
.cc-grafico {
    margin: 0;
}

.cc-grafico svg {
    display: block;
    width: 100%;
    height: auto;
}

.cc-grafico__guias line {
    stroke: var(--cc-border);
    stroke-dasharray: 3 4;
}

.cc-grafico text {
    fill: var(--cc-muted);
    font-size: 11px;
}

.cc-grafico__leyenda {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 8px;
    color: var(--cc-muted);
    font-size: 12px;
}

.cc-grafico__leyenda i {
    display: inline-block;
    width: 10px;
    height: 10px;
    margin-right: 6px;
    border-radius: 2px;
}
</style>
