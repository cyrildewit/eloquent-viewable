# Development Plan Next Major Version

## Overview

This document contains information about new features, changes, improvements and new ideas for the upcoming major version (`v3.0.0`).

### Table of contents

1. [Handling the storage of page views](#handling-the-storage-of-page-views)
2. [Facade and helper function](#facade-and-helper-function)

## Handling the storage of page views

In `v2` each view will be stored as a seperated record in the database. Each view contains some information about the viewer. For example a unique key that's stored inside a cookie on the user's computer. This package uses this key to filter out only the unique views.

<!--
2. [Multiple strategies](#multiple-strategies)
## Multiple strategies

At the moment, this package is created to work in the database.

A strategie is a class that handles most of the functionality-->
