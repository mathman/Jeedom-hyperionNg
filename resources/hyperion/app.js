const net = require('net');
const express = require('express');
const bodyParser = require('body-parser');
const npid = require('npid');
const passport = require('passport');
const Strategy = require('passport-http-bearer').Strategy;

const HyperionManager = require('./lib/HyperionManager');

const args = process.argv.slice(2);
const port = args[0];
const apiKey = args[1];

let webServer;
let hyperionManager;

let records = [
    { id: 1, username: 'user', token: apiKey, displayName: 'user', emails: [ { value: 'email@email.com' } ] }
];

(async () => {
	
	try {
        var pid = npid.create(args[2]);
        pid.removeOnExit();
    } catch (err) {
        console.log(err);
        process.exit(1);
    }
	
	passport.use(new Strategy(
		function(token, cb) {
			for (var i = 0, len = records.length; i < len; i++) {
				var record = records[i];
				if (record.token === token) {
					return cb(null, record);
				}
			}
			return cb(null, false);
		}
	));

    const app = express();
    
    app.use(bodyParser.json());
    app.use(bodyParser.urlencoded({ extended: true }));
		
	hyperionManager = new HyperionManager();
	
	app.get('/updateServerList', 
		passport.authenticate('bearer', { session: false }), 
		(req, res) => {
            
			res.setHeader('Content-Type', 'application/json');
			hyperionManager.updateServerList(req['body']['servers']);
			res.end(JSON.stringify('ok'));
		}
	)
	.get('/getServerInfo', 
		passport.authenticate('bearer', { session: false }), 
		async (req, res) => {
            
			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, hyperionManager.getServerToJson(req['query']['server']))));
		}
	)
    .get('/getInstanceInfo', 
		passport.authenticate('bearer', { session: false }), 
		async (req, res) => {
            
			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, hyperionManager.getInstanceToJson(req['query']['server'], req['query']['instance']))));
		}
	)
	.get('/getSourceInfo', 
		passport.authenticate('bearer', { session: false }), 
		async (req, res) => {
            
			res.setHeader('Content-Type', 'application/json');
			res.end(JSON.stringify(Object.assign({}, hyperionManager.getSourceToJson(req['query']['server'], req['query']['instance'], req['query']['source']))));
		}
	)
    .get('/setInstanceState', 
		passport.authenticate('bearer', { session: false }), 
		async (req, res) => {
            
			res.setHeader('Content-Type', 'application/json');
            hyperionManager.setInstance(req['query']['server'], req['query']['instance'], req['query']['state']);
			res.end('ok');
		}
	)
    .get('/setComponentState', 
		passport.authenticate('bearer', { session: false }), 
		async (req, res) => {
            
			res.setHeader('Content-Type', 'application/json');
            hyperionManager.setComponentState(req['query']['server'], req['query']['instance'], req['query']['component'], req['query']['state']);
			res.end('ok');
		}
	)
	.get('/setSource', 
		passport.authenticate('bearer', { session: false }), 
		async (req, res) => {
            
			res.setHeader('Content-Type', 'application/json');
            hyperionManager.setSource(req['query']['server'], req['query']['instance'], req['query']['source']);
			res.end('ok');
		}
	)
	.get('/setEffect', 
		passport.authenticate('bearer', { session: false }), 
		async (req, res) => {
            
			res.setHeader('Content-Type', 'application/json');
            hyperionManager.setEffect(req['query']['server'], req['query']['instance'], req['query']['effect'], req['query']['duration']);
			res.end('ok');
		}
	)
	.get('/setColor', 
		passport.authenticate('bearer', { session: false }), 
		async (req, res) => {
            
			var rgbColor = hexRgb(req['query']['color']);
			res.setHeader('Content-Type', 'application/json');
            hyperionManager.setColor(req['query']['server'], req['query']['instance'], rgbColor, req['query']['duration']);
			res.end('ok');
		}
	)
	.get('/setClear', 
		passport.authenticate('bearer', { session: false }), 
		async (req, res) => {
            
			res.setHeader('Content-Type', 'application/json');
            hyperionManager.clearEffect(req['query']['server'], req['query']['instance']);
			res.end('ok');
		}
	)
	.get('/stop', 
		passport.authenticate('bearer', { session: false }), 
		function(req, res) {
			process.exit(0);
		}
	)
    .use(function(req, res, next){
        res.setHeader('Content-Type', 'text/plain');
        res.status(404).send('Page introuvable !');
    });
    
    webServer = app.listen(port, function () {
        
        console.log("Api started on port " + port);
    });
	
	console.log("Program started");
})();

const hexCharacters = 'a-f\\d';
const match3or4Hex = `#?[${hexCharacters}]{3}[${hexCharacters}]?`;
const match6or8Hex = `#?[${hexCharacters}]{6}([${hexCharacters}]{2})?`;
const nonHexChars = new RegExp(`[^#${hexCharacters}]`, 'gi');
const validHexSize = new RegExp(`^${match3or4Hex}$|^${match6or8Hex}$`, 'i');
function hexRgb(hex, options = {}) {

	if (typeof hex !== 'string' || nonHexChars.test(hex) || !validHexSize.test(hex)) {
		throw new TypeError('Expected a valid hex string');
	}

	hex = hex.replace(/^#/, '');
	let alphaFromHex = 1;

	if (hex.length === 8) {
		alphaFromHex = Number.parseInt(hex.slice(6, 8), 16) / 255;
		hex = hex.slice(0, 6);
	}

	if (hex.length === 4) {
		alphaFromHex = Number.parseInt(hex.slice(3, 4).repeat(2), 16) / 255;
		hex = hex.slice(0, 3);
	}

	if (hex.length === 3) {
		hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
	}

	const number = Number.parseInt(hex, 16);
	const red = number >> 16;
	const green = (number >> 8) & 255;
	const blue = number & 255;
	const alpha = typeof options.alpha === 'number' ? options.alpha : alphaFromHex;

	if (options.format === 'array') {
		return [red, green, blue, alpha];
	}

	if (options.format === 'css') {
		const alphaString = alpha === 1 ? '' : ` / ${Number((alpha * 100).toFixed(2))}%`;
		return `rgb(${red} ${green} ${blue}${alphaString})`;
	}

	return {red, green, blue, alpha};
}

process.on("SIGINT", async () => {
    
    if (webServer) {
        
        webServer.close(() => {
            
            console.log('Http server closed.');
        });
    }
	
	if (hyperionManager) {
		
		hyperionManager.closeAllConnections()
	}
	
    process.removeAllListeners("SIGINT");
});