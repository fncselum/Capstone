// Script to download face-api.js model files
// Run this with Node.js to download the required models

const https = require('https');
const fs = require('fs');
const path = require('path');

const models = [
    {
        name: 'tiny_face_detector_model-weights_manifest.json',
        url: 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/tiny_face_detector_model-weights_manifest.json'
    },
    {
        name: 'tiny_face_detector_model-shard1',
        url: 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/tiny_face_detector_model-shard1'
    },
    {
        name: 'face_landmark_68_model-weights_manifest.json',
        url: 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-weights_manifest.json'
    },
    {
        name: 'face_landmark_68_model-shard1',
        url: 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-shard1'
    },
    {
        name: 'face_recognition_model-weights_manifest.json',
        url: 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-weights_manifest.json'
    },
    {
        name: 'face_recognition_model-shard1',
        url: 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-shard1'
    },
    {
        name: 'face_recognition_model-shard2',
        url: 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-shard2'
    }
];

function downloadFile(url, filename) {
    return new Promise((resolve, reject) => {
        const file = fs.createWriteStream(filename);
        https.get(url, (response) => {
            response.pipe(file);
            file.on('finish', () => {
                file.close();
                console.log(`Downloaded: ${filename}`);
                resolve();
            });
        }).on('error', (err) => {
            fs.unlink(filename, () => {}); // Delete the file on error
            reject(err);
        });
    });
}

async function downloadAllModels() {
    console.log('Downloading face-api.js model files...');
    
    for (const model of models) {
        try {
            await downloadFile(model.url, path.join(__dirname, model.name));
        } catch (error) {
            console.error(`Failed to download ${model.name}:`, error.message);
        }
    }
    
    console.log('Model download complete!');
}

downloadAllModels();
