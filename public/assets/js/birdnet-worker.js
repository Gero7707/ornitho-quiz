/**
 * birdnet-worker.js — Web Worker BirdNET pour OrnithoQuizz
 * Adapté de georg95/birdnet-web
 * Charge le modèle depuis Cloudflare R2 et analyse l'audio
 */

importScripts('https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest')

main()
async function main() {
    const params = new URL(location.href).searchParams
    const modelBaseUrl = params.get('modelUrl') || '/birdnet-model'

    await tf.setBackend('webgl')

    // 1. Charger le modèle principal
    const BirdNetJS = await predictModel(modelBaseUrl)
    postMessage({ message: 'warmup', progress: 70 })
    await BirdNetJS.warmup()

    // 2. Charger le modèle géographique
    postMessage({ message: 'load_geomodel', progress: 90 })
    const areaModel = await tf.loadGraphModel(`${modelBaseUrl}/area-model/model.json`)

    // 3. Charger les labels (français + anglais pour le nom latin)
    postMessage({ message: 'load_labels', progress: 95 })
    const birdsList   = (await fetch(`${modelBaseUrl}/labels/en_us.txt`).then(r => r.text())).split('\n')
    const birdsListFr = (await fetch(`${modelBaseUrl}/labels/fr.txt`).then(r => r.text())).split('\n')

    const birds = new Array(birdsList.length)
    for (let i = 0; i < birdsList.length; i++) {
        const parts = birdsList[i].split('_')
        birds[i] = {
            geoscore: 1,
            scientificName: parts[0],       // Nom latin (ex: "Erithacus rubecula")
            commonName: parts[1] || '',      // Nom anglais
            commonNameFr: birdsListFr[i] ? birdsListFr[i].split('_')[1] || '' : '',  // Nom français
        }
    }

    postMessage({ message: 'loaded', speciesCount: birds.length })

    // 4. Écouter les messages
    const MIN_AUDIO_CONFIDENCE = 0.1

    onmessage = async function({ data }) {
        if (data.message === 'predict') {
            const predictionList = await BirdNetJS.predict(
                tf.tensor(data.pcmAudio, [data.pcmAudio.length / 144000, 144000])
            )
            const prediction = []
            for (let batch = 0; batch < predictionList.length; batch++) {
                for (let i = 0; i < predictionList[batch].length; i++) {
                    const confidence = predictionList[batch][i]
                    if (confidence > MIN_AUDIO_CONFIDENCE && birds[i].geoscore > data.minAreaConfidence) {
                        prediction.push({
                            scientificName: birds[i].scientificName,
                            commonName: birds[i].commonName,
                            commonNameFr: birds[i].commonNameFr,
                            confidence: confidence,
                            geoscore: birds[i].geoscore,
                            batch: batch
                        })
                    }
                }
            }
            // Trier par confiance décroissante
            prediction.sort((a, b) => b.confidence - a.confidence)
            postMessage({ message: 'predict', prediction, chunkIndex: data.chunkIndex })
        }

        if (data.message === 'area-scores') {
            tf.engine().startScope()
            const startOfYear = new Date(new Date().getFullYear(), 0, 1)
            startOfYear.setDate(startOfYear.getDate() + (1 - (startOfYear.getDay() % 7)))
            const week = Math.round((new Date() - startOfYear) / 604800000) + 1
            const areaTensor = tf.tensor([[data.latitude, data.longitude, week]])
            const areaScores = await areaModel.predict(areaTensor).data()
            tf.engine().endScope()
            for (let i = 0; i < birds.length; i++) {
                birds[i].geoscore = areaScores[i]
            }
            postMessage({ message: 'area-scores' })
        }
    }
}

async function predictModel(baseUrl) {
    const BirdNetJS = await tf.loadLayersModel(`${baseUrl}/model.json`, {
        onProgress: (progress) => postMessage({ message: 'load_model', progress: progress * 70 | 0 })
    })
    async function predict(signal) {
        const resTensor = BirdNetJS.predict(signal)
        signal.dispose()
        const result = await resTensor.array()
        resTensor.dispose()
        return result
    }
    return {
        async warmup() {
            await predict(tf.zeros([1, 144000]))
        },
        predict
    }
}

// ─── Couche custom MelSpecLayerSimple (requise par le modèle) ───
class MelSpecLayerSimple extends tf.layers.Layer {
    constructor(config) {
        super(config)
        this.sampleRate = config.sampleRate
        this.specShape = config.specShape
        this.frameStep = config.frameStep
        this.frameLength = config.frameLength
        this.melFilterbank = tf.tensor2d(config.melFilterbank)
    }
    build(inputShape) {
        this.magScale = this.addWeight(
            'magnitude_scaling', [], 'float32',
            tf.initializers.constant({ value: 1.23 })
        )
        super.build(inputShape)
    }
    computeOutputShape(inputShape) {
        return [inputShape[0], this.specShape[0], this.specShape[1], 1]
    }
    call(inputs) {
        return tf.tidy(() => {
            inputs = inputs[0]
            return tf.stack(inputs.split(inputs.shape[0]).map((input) => {
                let spec = input.squeeze()
                spec = tf.sub(spec, tf.min(spec, -1, true))
                spec = tf.div(spec, tf.max(spec, -1, true).add(0.000001))
                spec = tf.sub(spec, 0.5)
                spec = tf.mul(spec, 2.0)
                spec = tf.engine().runKernel('STFT', { signal: spec, frameLength: this.frameLength, frameStep: this.frameStep })
                spec = tf.matMul(spec, this.melFilterbank)
                spec = spec.pow(2.0)
                spec = spec.pow(tf.div(1.0, tf.add(1.0, tf.exp(this.magScale.read()))))
                spec = tf.reverse(spec, -1)
                spec = tf.transpose(spec)
                spec = spec.expandDims(-1)
                return spec
            }))
        })
    }
    static get className() { return 'MelSpecLayerSimple' }
}
tf.serialization.registerClass(MelSpecLayerSimple)

// ─── Kernel STFT WebGL ─────────────────────────────────────────
tf.registerKernel({
    kernelName: 'STFT',
    backendName: 'webgl',
    kernelFunc: ({ backend, inputs: { signal, frameLength, frameStep } }) => {
        const innerDim = frameLength / 2
        const batch = (signal.size - frameLength + frameStep) / frameStep | 0
        let currentTensor = backend.runWebGLProgram({
            variableNames: ['x'],
            outputShape: [batch, frameLength],
            userCode: `
            void main() {
                ivec2 coords = getOutputCoords();
                int p = coords[1] % ${innerDim};
                int k = 0;
                for (int i = 0; i < ${Math.log2(innerDim)}; ++i) {
                    if ((p & (1 << i)) != 0) { k |= (1 << (${Math.log2(innerDim) - 1} - i)); }
                }
                int i = 2 * k;
                if (coords[1] >= ${innerDim}) {
                    i = 2 * (k % ${innerDim}) + 1;
                }
                int q = coords[0] * ${frameLength} + i;
                float val = getX((q / ${frameLength}) * ${frameStep} + q % ${frameLength});
                float cosArg = ${2.0 * Math.PI / frameLength} * float(q);
                float mul = 0.5 - 0.5 * cos(cosArg);
                setOutput(val * mul);
            }`
        }, [signal], 'float32')
        for (let len = 1; len < innerDim; len *= 2) {
            let prevTensor = currentTensor
            currentTensor = backend.runWebGLProgram({
                variableNames: ['x'],
                outputShape: [batch, innerDim * 2],
                userCode: `void main() {
                    ivec2 coords = getOutputCoords();
                    int batch = coords[0];
                    int i = coords[1];
                    int k = i % ${innerDim};
                    int isHigh = (k % ${len * 2}) / ${len};
                    int highSign = (1 - isHigh * 2);
                    int baseIndex = k - isHigh * ${len};
                    float t = ${Math.PI / len} * float(k % ${len});
                    float a = cos(t);
                    float b = sin(-t);
                    float oddK_re = getX(batch, baseIndex + ${len});
                    float oddK_im = getX(batch, baseIndex + ${len + innerDim});
                    if (i < ${innerDim}) {
                        float evenK_re = getX(batch, baseIndex);
                        setOutput(evenK_re + (oddK_re * a - oddK_im * b) * float(highSign));
                    } else {
                        float evenK_im = getX(batch, baseIndex + ${innerDim});
                        setOutput(evenK_im + (oddK_re * b + oddK_im * a) * float(highSign));
                    }
                }`
            }, [currentTensor], 'float32')
            backend.disposeIntermediateTensorInfo(prevTensor)
        }
        const real = backend.runWebGLProgram({
            variableNames: ['x'],
            outputShape: [batch, innerDim + 1],
            userCode: `void main() {
                ivec2 coords = getOutputCoords();
                int batch = coords[0];
                int i = coords[1];
                int zI = i % ${innerDim};
                int conjI = (${innerDim} - i) % ${innerDim};
                float Zk0 = getX(batch, zI);
                float Zk1 = getX(batch, zI+${innerDim});
                float Zk_conj0 = getX(batch, conjI);
                float Zk_conj1 = -getX(batch, conjI+${innerDim});
                float t = ${-2 * Math.PI} * float(i) / float(${innerDim * 2});
                float diff0 = Zk0 - Zk_conj0;
                float diff1 = Zk1 - Zk_conj1;
                float result = (Zk0 + Zk_conj0 + cos(t) * diff1 + sin(t) * diff0) * 0.5;
                setOutput(result);
            }`
        }, [currentTensor], 'float32')
        backend.disposeIntermediateTensorInfo(currentTensor)
        return real
    }
})