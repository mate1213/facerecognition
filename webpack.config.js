const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
    'sidebar': path.join(__dirname, 'src', 'sidebarloader.js'),
    'admin': path.join(__dirname, 'src', 'admin.js'),
    'personal': path.join(__dirname, 'src', 'personal.js'),
}

// Add Handlebars loader
webpackConfig.module.rules.push({
    test: /\.handlebars$/,
    loader: 'handlebars-loader',
    options: {
        partialDirs: [path.join(__dirname, 'src/templates/partials')]
    },
})

module.exports = webpackConfig
