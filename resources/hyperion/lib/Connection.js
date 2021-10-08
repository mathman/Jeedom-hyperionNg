const net = require('net');
const { EventEmitter } = require('events');
const cryptoRandomString = require('crypto-random-string');

class Connection extends EventEmitter {

    constructor(host, port) {
		
		super();
        this.host = host;
		this.port = port;
		this.authenticated = false;
		this.connected = false;
		this.token = null;
		this.message = '';
		
		this.client = new net.Socket();
		var self = this;
		this.client.on('connect', function(connection) {

			self.onConnected();
			self.emit('connected');
		});
		this.client.on('error', function(error) {
			
			self.closeConnection();
			self.emit('connection error', error);
		});
		this.client.on('close', function() {
			
			self.closeConnection();
			self.emit('connection close');
		});
		this.client.on('data', function(message) {

			self.onMessage(message);
		});
    }
	
	closeConnection() {
		
		if (typeof this.client !== 'undefined' && this.client !== null) {
			this.client.end();
		}
		this.authenticated = false;
		this.connected = false;
		this.token = null;
	}
	
	isConnected() {
		
		return this.connected;
	}
	
	isAuthenticated() {
		
		return this.authenticated;
	}
	
	onMessage(msg) {
		
		this.message += msg.toString();
		try {
			
			var messageParsed = JSON.parse(this.message);
			switch (messageParsed.command) {
				case 'authorize-tokenRequired':
					this.onTokenRequiredMessage(messageParsed);
					break;
				case 'authorize-login':
					this.onAuthorizeLogin(messageParsed);
					break;
				default:
					this.emit('message', messageParsed);
					break;
			}
			this.message = '';
		}
		catch (e) {
			
		}
	}
	
	connect(token) {
		
		this.token = token;
		if (!this.isAuthenticated()) {
			
			if (!this.isConnected()) {
				
				this.client.connect(this.port, this.host);
			}
		}
	}
	
	onConnected() {
		
		this.connected = true;
		this.requestAuthorization();
	}
	
	requestAuthorization() {
		
		if (this.isConnected()) {
			
			this.client.write('{"command" : "authorize","subcommand" : "tokenRequired"}\n');
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
			
			this.client.write('{"command" : "authorize","subcommand" : "login","token" : "' + this.token + '"}\n');
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
			
			this.client.write(command);
			return true;
		}
		return false;
	}
}

module.exports = Connection;