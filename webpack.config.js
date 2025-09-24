const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
    'sidebar': path.join(__dirname, 'src', 'sidebarloader.js'),
    'admin': path.join(__dirname, 'src', 'admin.js'),
    'personal': path.join(__dirname, 'src', 'personal.js'),
}

// Add rule for handlebars
webpackConfig.module.rules.push({
    test: /\.handlebars$/,
    loader: 'handlebars-loader',
    options: {
        // optional: you can add helpers/partials dirs if needed
        // helperDirs: [path.resolve(__dirname, 'src/helpers')],
        partialDirs: [path.resolve(__dirname, 'src/partials')],
    },
})

webpackConfig.module.rules.push({
    test: /\.m?js$/,
    exclude: /(node_modules|bower_components)/,
    use: {
        loader: 'babel-loader',
        options: {
            presets: [
                [
                    '@babel/preset-env',
                    {
                        targets: '> 0.25%, not dead',
                        useBuiltIns: 'usage',
                        corejs: 3,
                    },
                ],
            ],
        },
    },
})

webpackConfig.resolve = {
    alias: {
        '@partials': path.resolve(__dirname, 'src/partials'),
        '@helpers': path.resolve(__dirname, 'src/helpers'),
    },
    extensions: ['.js', '.json', '.vue', '.handlebars'],
}



module.exports = webpackConfig
