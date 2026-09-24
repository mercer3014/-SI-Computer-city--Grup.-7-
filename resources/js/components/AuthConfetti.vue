<script setup lang="ts">
import { animate } from 'motion-v';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type Shape = 'rect' | 'sq' | 'strip' | 'dot';

type Piece = {
    id: number;
    color: string;
    shape: Shape;
    width: number;
    height: number;
    angle: number;
    speed: number;
    spin: number;
    delay: number;
    gravity: number;
    drift: number;
    duration: number;
    peak: number;
    along: number;
    lift: number;
};

const COLORS = ['#FF3D8F', '#B01050', '#5CE1FF', '#D97706', '#3DDC97', '#FFD166', '#FFFFFF'];
const SHAPES: Shape[] = ['rect', 'sq', 'strip', 'dot'];
const COUNT = 72;
const SPREAD = (78 * Math.PI) / 180;

function makePieces(): Piece[] {
    const pieces: Piece[] = [];

    for (let index = 0; index < COUNT; index++) {
        const shape = SHAPES[Math.floor(Math.random() * SHAPES.length)];
        const scalar = 0.55 + Math.random() * 1.15;
        const size =
            shape === 'strip'
                ? [3, 16]
                : shape === 'sq'
                  ? [8, 8]
                  : shape === 'dot'
                    ? [6, 6]
                    : [7, 13];

        pieces.push({
            id: index,
            color: COLORS[Math.floor(Math.random() * COLORS.length)],
            shape,
            width: Math.max(3, Math.round(size[0] * scalar)),
            height: Math.max(4, Math.round(size[1] * scalar)),
            angle: -Math.PI / 2 + (Math.random() - 0.5) * SPREAD,
            speed: 210 + Math.random() * 320,
            spin: (Math.random() < 0.5 ? -1 : 1) * (280 + Math.random() * 980),
            delay: Math.random() * 0.07,
            gravity: 240 + Math.random() * 340,
            drift: (Math.random() - 0.5) * 140,
            duration: 1.35 + Math.random() * 1.1,
            peak: 0.22 + Math.random() * 0.16,
            along: (index + Math.random() * 0.8) / COUNT,
            lift: Math.random(),
        });
    }

    return pieces;
}

const pieces = makePieces();
const root = ref<HTMLElement | null>(null);
const controls: Array<{ stop: () => void }> = [];

function stopBurst(): void {
    for (let index = 0; index < controls.length; index++) {
        controls[index]?.stop();
    }

    controls.length = 0;
}

function letterSlots(heading: HTMLElement): DOMRect[] {
    const slots: DOMRect[] = [];
    const walker = document.createTreeWalker(heading, NodeFilter.SHOW_TEXT);

    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        const value = node.textContent ?? '';

        for (let index = 0; index < value.length; index++) {
            if (/\s/.test(value[index] ?? '')) {
                continue;
            }

            const range = document.createRange();
            range.setStart(node, index);
            range.setEnd(node, index + 1);
            const rect = range.getBoundingClientRect();

            if (rect.width > 0 && rect.height > 0) {
                slots.push(rect);
            }
        }
    }

    return slots;
}

function headingSlots(layer: HTMLElement): DOMRect[] {
    const heading = layer.closest('.cc-form--success')?.querySelector('h1');

    if (heading) {
        const letters = letterSlots(heading);

        if (letters.length) {
            return letters;
        }

        const range = document.createRange();
        range.selectNodeContents(heading);
        const text = range.getBoundingClientRect();

        if (text.width > 8) {
            return [text];
        }

        return [heading.getBoundingClientRect()];
    }

    return [layer.getBoundingClientRect()];
}

function originFor(slots: DOMRect[], piece: Piece): { x: number; y: number } {
    const slot = slots[Math.min(slots.length - 1, Math.floor(piece.along * slots.length))] ?? slots[0];

    return {
        x: slot.left + slot.width * piece.lift,
        y: slot.top + slot.height * (0.15 + piece.lift * 0.7),
    };
}

function startBurst(): void {
    const layer = root.value;

    if (!layer || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    stopBurst();

    const nodes = layer.querySelectorAll<HTMLElement>('[data-piece]');
    const slots = headingSlots(layer);

    for (let index = 0; index < nodes.length; index++) {
        const node = nodes[index];
        const piece = pieces[index];

        if (!node || !piece) {
            continue;
        }

        const origin = originFor(slots, piece);
        node.style.left = `${origin.x - piece.width / 2}px`;
        node.style.top = `${origin.y - piece.height / 2}px`;

        const launch = piece.angle + (piece.along - 0.5) * 0.55;
        const vx = Math.cos(launch) * piece.speed;
        const vy = Math.sin(launch) * piece.speed;
        const peakY = vy * 0.72;
        const midY = peakY * 0.25 + piece.gravity * 0.22;
        const endY = piece.gravity;

        controls.push(
            animate(
                node,
                {
                    x: [0, vx * 0.08, vx * 0.42, vx * 0.78 + piece.drift * 0.4, vx + piece.drift],
                    y: [0, peakY * 0.12, peakY, midY, endY],
                    rotate: [0, piece.spin * 0.08, piece.spin * 0.28, piece.spin * 0.68, piece.spin],
                    scale: [0.55, 0.85, 1, 0.95, 0.65],
                    opacity: [1, 1, 1, 1, 0],
                },
                {
                    duration: piece.duration,
                    delay: piece.delay,
                    times: [0, 0.08, piece.peak, 0.62 + Math.random() * 0.12, 1],
                    ease: [0.18, 0.72, 0.22, 1],
                    onComplete: () => {
                        node.style.willChange = 'auto';
                    },
                },
            ),
        );
    }
}

onMounted(() => {
    startBurst();
    document.addEventListener('cc-confetti-replay', startBurst);
});

onBeforeUnmount(() => {
    document.removeEventListener('cc-confetti-replay', startBurst);
    stopBurst();
});
</script>

<template>
    <div ref="root" class="cc-confetti" aria-hidden="true">
        <span
            v-for="piece in pieces"
            :key="piece.id"
            data-piece
            class="cc-confetti__piece"
            :data-shape="piece.shape"
            :style="{
                width: `${piece.width}px`,
                height: `${piece.height}px`,
                background: piece.color,
            }"
        />
    </div>
</template>
