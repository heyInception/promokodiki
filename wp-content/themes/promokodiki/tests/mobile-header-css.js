'use strict';

const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const css = fs.readFileSync( path.join( __dirname, '../assets/css/overrides.css' ), 'utf8' );
const narrow = css.match( /@media \(max-width: 480px\)[\s\S]*$/ );

assert.ok( narrow, 'narrow-phone styles exist' );
assert.match( narrow[ 0 ], /\.nav__favorite-button[\s\S]*font-size:\s*12px/, 'favorite text shrinks on narrow phones' );
assert.match( narrow[ 0 ], /\.nav__favorite-button[\s\S]*padding:\s*[^;]+/, 'favorite padding shrinks on narrow phones' );

console.log( 'Mobile header CSS contract passed.' );
