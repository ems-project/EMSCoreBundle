'use strict'

import FileUploader from '@elasticms/file-uploader'

async function resizeImage(hashAlgo, initUpload, fileHandler) {
    return  new Promise((resolve, reject) => {
        const imageTypes = ['image/png','image/jpeg','image/webp']
        if (!imageTypes.includes(fileHandler.type)) {
            resolve(null)
        }

        let fileHash = null
        const reader = new FileReader()
        const imageMaxSize = document.body.dataset.imageMaxSize
        reader.onload = function (e) {
            const  image = new Image()
            image.onload = function (imageEvent) {
                const canvas = document.createElement('canvas')
                let width = image.width
                let height = image.height
                if (width <= imageMaxSize && height <= imageMaxSize) {
                    resolve(null)
                }
                if (width > height) {
                    if (width > imageMaxSize) {
                        height = Math.round(height * imageMaxSize / width)
                        width = imageMaxSize
                    }
                } else {
                    if (height > imageMaxSize) {
                        width = Math.round(width * imageMaxSize / height)
                        height = imageMaxSize
                    }
                }
                canvas.width = width
                canvas.height = height
                canvas.getContext('2d').drawImage(image, 0, 0, width, height)
                const dataUrl = canvas.toDataURL(fileHandler.type)
                const resizedImage = dataUrlToBlob(dataUrl)
                let basename = fileHandler.name
                let extension = ''
                if(basename.lastIndexOf('.') !== -1) {
                    extension = basename.substring(basename.lastIndexOf("."))
                    basename = basename.substring(0, basename.lastIndexOf("."))
                }
                resizedImage.name = `${basename}_${width}x${height}${extension}`

                const resizedImageUploader = new FileUploader({
                    file: resizedImage,
                    algo: hashAlgo,
                    initUrl: initUpload,
                    emsListener: self,
                    onHashAvailable: function(hash){
                        fileHash = hash
                    },
                    onUploaded: function(assetUrl, previewUrl){
                        resolve({
                            hash: fileHash,
                            url: previewUrl,
                        })
                    },
                    onError: function(message, code){
                        reject(`Error ${code} during upload of resized image with message: ${message}`)
                    },
                })
            }
            image.src = e.target.result
        }
        reader.readAsDataURL(fileHandler)
    })
}

function dataUrlToBlob(dataUrl) {
    const BASE64_MARKER = ';base64,'
    if (dataUrl.indexOf(BASE64_MARKER) === -1) {
        const parts = dataUrl.split(',')
        const contentType = parts[0].split(':')[1]
        const raw = parts[1]

        return new Blob([raw], {type: contentType})
    }

    const parts = dataUrl.split(BASE64_MARKER)
    const contentType = parts[0].split(':')[1]
    const raw = window.atob(parts[1])
    const rawLength = raw.length

    const uInt8Array = new Uint8Array(rawLength)

    for (let i = 0; i < rawLength; ++i) {
        uInt8Array[i] = raw.charCodeAt(i)
    }

    return new Blob([uInt8Array], {type: contentType})
}

export { resizeImage, dataUrlToBlob }
