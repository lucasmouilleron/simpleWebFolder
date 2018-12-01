###################################################################################
# IMPORTS
###################################################################################
import signal
import json
from threading import Thread
from gevent.wsgi import WSGIServer
from flask import Flask, request, jsonify, send_from_directory, redirect
from flask_cors import CORS
import helper as h

###################################################################################
# CONFIG
###################################################################################
SERVER_TIMEZONE = "Europe/Paris"


###################################################################################
# FUNCTIONS
###################################################################################

###################################################################################
###################################################################################
###################################################################################
class Server(Thread):

    ###################################################################################
    def __init__(self, port, ssl=False, certificateKeyFile=None, certificateCrtFile=None, fullchainCrtFile=""):
        Thread.__init__(self)
        self.app = Flask(__name__)
        self.port = port
        self.ssl = ssl
        self.certificateKeyFile = certificateKeyFile
        self.certificateCrtFile = certificateCrtFile
        self.fullchainCrtFile = fullchainCrtFile
        self.httpServer = None

        self._addRoute("/hello", self._routeHello, ["GET"])
        self._addRoute("/", self._routeDefault, ["GET", "POST"])
        self._addRouteRaw("/<path:path>", self._routeDefault, ["GET", "POST"])

    ###################################################################################
    def run(self):
        CORS(self.app)
        if self.ssl: self.httpServer = WSGIServer(("0.0.0.0", self.port), server.app, log=None, keyfile=self.certificateKeyFile, certfile=self.certificateCrtFile, ca_certs=self.fullchainCrtFile)
        else: self.httpServer = WSGIServer(("0.0.0.0", self.port), server.app, log=None)
        self.httpServer.serve_forever()

    ###################################################################################
    def stop(self):
        self.httpServer.stop()

    ###################################################################################
    def _addRoute(self, rule, callback, methods=["GET"], endpoint=None):
        def callbackReal(*args, **kwargs):
            try: return jsonify(callback(*args, **kwargs))
            except Exception as e:
                h.logWarning("Error processing request", request.data.decode("utf-8"), request.endpoint, h.formatException(e))
                return jsonify({"result": 500, "hint": h.formatException(e)})

        if endpoint is None: endpoint = h.uniqueID()
        self.app.add_url_rule(rule, endpoint, callbackReal, methods=methods)

    ###################################################################################
    def _addRouteRaw(self, rule, callback, methods, endpoint=None):
        if endpoint is None: endpoint = h.uniqueID()
        self.app.add_url_rule(rule, endpoint, callback, methods=methods)

    ###################################################################################
    def _routeHello(self):
        return {"result": 200, "world": h.now(), "version": h.dictionnaryDeepGet(h.CONFIG, "version", default=0)}

    ###################################################################################
    def _routeDefault(self, path=None):
        return path
        return send_from_directory(h.DATA_FOLDER, path)


###################################################################################
# MAIN
###################################################################################
h.displaySplash()

server = Server(h.dictionnaryDeepGet(h.CONFIG, "server", "port", default=5000), h.dictionnaryDeepGet(h.CONFIG, "server", "ssl", default=False), h.CERTIFICATE_KEY_FILE, h.CERTIFICATE_CRT_FILE, h.FULLCHAIN_CRT_FILE)
server.start()
h.logInfo("Server started", server.port, server.ssl)

signal.pause()
server.stop()
