let http = require("http");

// Goal here is to read node requests, pass it over to
// php's cgi handler, and then get the response back into node

let middleware = require("node-phpcgi")({
    documentRoot: __dirname,
    handler: "path\\to\\your\\php-cgi.exe"
});

let app = http.createServer((req, res) => {
    middleware(req, res, () => {});
}).listen(8080);