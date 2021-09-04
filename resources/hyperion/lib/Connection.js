const WebSocketClient = require('websocket').client;
const { EventEmitter } = require('events');
const cryptoRandomString = require('crypto-random-string');

class Connection extends EventEmitter {

    constructor(host, port) {
		
		super();
        this.host = host;
		this.port = port;
		this.authenticated = false;
		this.token = null;
		
		this.wsClient = new WebSocketClient({tlsOptions: {rejectUnauthorized: false}});
		this.connection = null;
		var self = this;
		this.wsClient.on('connectFailed', function(error) {
			
			self.emit('connectFailed', error);
		});
		this.wsClient.on('connect', function(connection) {
			
			self.connection = connection;
			self.connection.on('error', function(error) {
			
				self.closeConnection();
				self.emit('connection error', error);
			});
			self.connection.on('close', function() {
			
				self.closeConnection();
				self.emit('connection close');
			});
			self.connection.on('message', function(message) {

				self.onMessage(message);
				self.emit('message', message);
			});
			
			self.onConnected();
			self.emit('connected');
		});
    }
	
	closeConnection() {
		
		if (typeof this.connection !== 'undefined' && this.connection !== null) {
			if (this.connection.connected) {
				
				this.connection.close();
			}
		}
		this.connection = null;
		this.authenticated = false;
		this.token = null;
	}
	
	isConnected() {
		
		if (typeof this.connection !== 'undefined' && this.connection !== null) {
			
			return this.connection.connected;
		}
		return false;
	}
	
	isAuthenticated() {
		
		return this.authenticated;
	}
	
	onMessage(msg) {
		
		var messageParsed = JSON.parse(msg.utf8Data);
		switch (messageParsed.command) {
			case 'authorize-tokenRequired':
				this.onTokenRequiredMessage(messageParsed);
				break;
			case 'authorize-login':
				this.onAuthorizeLogin(messageParsed);
				break;
			default:
				break;
		}
	}
	
	connect(token) {
		
		this.token = token;
		if (!this.isAuthenticated()) {
			
			if (!this.isConnected()) {
				
				this.wsClient.connect('wss://' + this.host + ':' + this.port + '/');
			}
		}
	}
	
	onConnected() {
		
		if (this.connection.connected) {
			
			this.requestAuthorization();
		}
	}
	
	requestAuthorization() {
		
		if (this.isConnected()) {
			
			this.connection.send('{"command" : "authorize","subcommand" : "tokenRequired"}\n');
		}
	}
	
	onTokenRequiredMessage(authorizeMsg) {
		
		if (authorizeMsg.info.required === false) {
			
			this.authenticate();
			return;
		}
		this.login();
		return;
	}
	
	login() {
		
		if (this.isConnected()) {
			
			this.connection.send('{"command" : "authorize","subcommand" : "login","token" : "' + this.token + '"}\n');
		}
	}
	
	onAuthorizeLogin(loginMsg) {
		
		if (loginMsg.success !== true) {
			
			console.log(loginMsg.error);
			this.token = null;
			this.onErrorAuthenticated('error token');
			return;
		}
		this.authenticate();
	}
	
	authenticate() {
		
		this.authenticated = true;
		this.emit('authenticated');
	}
	
	onErrorAuthenticated(error) {
		
		this.closeConnection();
		this.emit('authenticate error', error);
	}
	
	sendCommand(command) {
		
		if (this.isConnected() && this.isAuthenticated()) {
			
			this.connection.send(command);
			return true;
		}
		return false;
	}
}

module.exports = Connection;