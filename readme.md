# Coffeepress

### Providing Coffeescript and Backbone project structure and compiling

---

Filesystem Structure:

    |- includes
        |- generic.coffee
    |- mixins
        |- cookie.coffee
        |- helperfunc.coffee
        |- helpertwo.coffee
    |- modules
        |- modA.coffee
        |- modB.coffee
    |- pages
        |- a.coffee
        |- b.coffee
    |- raw
        |- backbone.js
        |- underscore.js
    |- routers
    |- templates
        |- home
            |- side.coffee
            |- main.coffee
        |- pages
            |- about.coffee
            |- new.coffee
    |- app.php.coffee

---

# Overview:

When compiling coffeescript files, it can be difficult to logically organize and compile files into a single asset, especially when including dynamically generated variables and.

The coffeepress library gives the flexibility of allowing the developer to specify how items are laid out, 
with tree reserved folder names:

  - mixins
  - templates
  - raw

## How to Use:

    <?= Coffee::pages('pageone') ?>

would by default look for 'pages/pageone.coffee' and include the script in the coffee template file...

    <?= Coffee::pages(array(
      'pageone',
      'pagetwo'
    )) ?>

would look for and include both 'pages/pageone', and 'pages/pagetwo' and include in the template file.

---

## Reserved Directories:

### /mixins/ 

Mixins require the underscore.js library, and gives a simple mechanism for saving 

### /templates/

Templates are loaded using the module pattern, so they can be included at any state

### /raw/

Raw javascript files are checked 