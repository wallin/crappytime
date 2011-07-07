var WinFactory = {
   addRoundTop: function(parent) {
      var round = [ 4,2,1 ];
      for(var i in round) {
         var o = document.createElement('div');
         o.className = "round" + round[i];
         parent.appendChild(o);
      }
   },
   addRoundBottom: function(parent) {
      var round = [ 1,2,4 ];
      for(var i in round) {
         var o = document.createElement('div');
         o.className = "round" + round[i];
         parent.appendChild(o);
      }         
   },
   addHeadActions: function(window) {
      var o = document.createElement('a');
      o.appendChild(document.createTextNode('x'));
      o.className = "win_close";
      o.href = "javascript:;";
      o._wref = window;
      o.onclick = WinManager.close;
      window.head.appendChild(o);
      
      o = document.createElement('a');
      o.appendChild(document.createTextNode('_'));
      o.className = "win_minimize";
      o.href = "javascript:;";
      o._wref = window;
      o.onclick = WinManager.minimize;
      window.head.appendChild(o);            
   }
}

var WinManager = {
   windows: new Array(),
   create: function(id, parent, options) {
      var win = new Window(id, parent, options);
      if(options && options.name) {
         win.name = options.name;
      } else {
         win.name = "Window " + this.windows.length;
      }
      this.windows.push(win);
      return win;
   },
   destroy: function(id) {
      var len = WinManager.windows.length;
      var newwins = Array();
      for(var i = 0; i < len; i++) {
         if(WinManager.windows[i].window.id == id) {
            WinManager.windows[i].window.parentNode.removeChild(WinManager.windows[i].window);
            while (WinManager.windows[i].window.hasChildNodes()) {
               WinManager.windows[i].window.removeChild(WinManager.windows[i].window.lastChild);
            }
         } else {
            newwins.push(WinManager.windows[i]);
         }
      }
      WinManager.windows = newwins;
   },
   minimize: function(wref) {
      wref = typeof(this._wref) == "object" ? this._wref : wref;
      var action;
      if(wref.open) {
         wref.canvas.style.display = "none";
         wref.open = false;
         action = "minimize";
      } else if(!wref.open){
         wref.canvas.style.display = "block";
         wref.open = true;
         action = "maximize";
      }
      $(document).trigger("window."+wref.name+"."+action);
   },
   close: function(wref) {
      wref = typeof(this._wref) == "object" ? this._wref: wref;
      var action;
      if(wref.window.style.display == "none") {
         wref.window.style.display = "block";
         action = "open";
      } else {
         wref.window.style.display = "none";
         action = "close";
      }
      $(document).trigger("window."+wref.name+"."+action);
   }
};
   
function Window(id, parent, options) {
   this.parent = parent;
   /* Create root window */
   this.window = document.createElement('div');
   this.window.id = id;
   this.open = true;
  
   /* Create title area if desired */
   this.head = document.createElement('div');
   this.window.appendChild(this.head);
   if(options && options.round) {
      WinFactory.addRoundTop(this.head);
   }      
   if(options && options.title) {
      this.head.className = "head";   
      if(options.controls) {
         WinFactory.addHeadActions(this);
      }
      this.title = document.createElement('div');
      this.title.className = "title";
      this.head.appendChild(this.title);
   }
   
   /* Create canvas area */
   this.canvas = document.createElement('div');
   this.canvas.className = "content";
   this.window.appendChild(this.canvas);
   
   /* Create footer area if desired */
   this.footer = document.createElement('div');
   this.window.appendChild(this.footer);
   if(options && options.gutter) {
      this.footer.className = "footer";   
      this.gutter = document.createElement('div');
      this.gutter.className = "gutter";            
      this.footer.appendChild(this.gutter);
   }
   if(options && options.round) {
      WinFactory.addRoundBottom(this.footer);
   }           
   
   /* Attach window to parent */
   this.parent = document.getElementById(parent);
   if(!this.parent) { throw "Parent element not found!"; }
   this.parent.appendChild(this.window);
};