const path = require('path');
const webpack = require('webpack');

module.exports = {
    context: path.resolve(__dirname, './'),
    entry: {
        default: './Resources/assets/app.js',
    },
    output: {
        path: path.resolve(__dirname, 'Resources/public/js'),
        filename: '[name].bundle.js',
        //publicPath: '../bundles/emscore/',
    },
};