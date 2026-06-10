/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [ './src/**/*.{js,jsx}' ],
	theme: {
		extend: {
			colors: {
				agenpress: {
					primary: '#2563eb',
					dark: '#1e293b',
					light: '#f8fafc',
				},
			},
		},
	},
	plugins: [],
	prefix: 'ap-',
};
