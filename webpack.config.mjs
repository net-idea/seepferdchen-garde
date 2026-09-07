import Encore from '@symfony/webpack-encore';

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on this config file directly (e.g. `webpack` CLI).
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or subdirectory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry('app', './assets/app.js')

    // Images referenced from Twig (asset('build/images/...')) – hashed and listed in manifest.json
    .copyFiles({
        from: './assets/images',
        pattern: /riccardo-advertisement(-\d+)?\.(jpe?g|webp)$/,
        to: 'images/[path][name].[hash:8].[ext]',
    })

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()

    // Displays build status system notifications to the user
    // .enableBuildNotifications()

    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // configure Babel
    // .configureBabel((config) => {
    //     config.plugins.push('@babel/a-babel-plugin');
    // })

    // Babel 8: polyfills are injected by babel-plugin-polyfill-corejs3 (replaces preset-env's useBuiltIns/corejs)
    .configureBabel((babelConfig) => {
        babelConfig.plugins.push([
            'babel-plugin-polyfill-corejs3',
            { method: 'usage-global', version: '3.50' },
        ]);
    })
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = false;
        config.corejs = undefined;
    })

    // enables Sass/SCSS support (silence deprecation noise coming from Bootstrap's own SCSS)
    .enableSassLoader((options) => {
        options.sassOptions = {
            ...(options.sassOptions || {}),
            quietDeps: true,
            silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls', 'if-function'],
        };
    })

    // enable TypeScript support
    .enableTypeScriptLoader()

    // dev-server: enable HMR/live reload and watch Twig/PHP changes
    .configureDevServerOptions((options) => {
        options.hot = true;
        options.liveReload = true;
        options.client = { overlay: true, progress: true };
        options.watchFiles = [
            'assets/**/*',
            'templates/**/*.twig',
            'src/**/*.php'
        ];
        // Bind to localhost for Symfony dev-server auto-detection
        options.host = 'localhost';
        options.port = 8080;
        options.headers = {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET,HEAD,PUT,PATCH,POST,DELETE',
            'Access-Control-Allow-Headers': 'X-Requested-With, content-type, Authorization'
        };
    })

    // uncomment if you use React
    //.enableReactPreset()

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    //.enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    //.autoProvidejQuery()
;

export default await Encore.getWebpackConfig();
