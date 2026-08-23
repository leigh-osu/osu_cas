# Forages

Image Album could move data into Fields on the Image Media only.

## Checklist

- [x] Image album data copied into media
- [x] Extra Book/Page fields imported into page
- [x] All custom content types done.

#### Node types with Paragraph fields

Listing the dependencies of the type can help you understand what needs to be configured for the migration.

#### Group Types

If a node needs to be included in a Group or be Group aware a custom migration is needed for those types other than
Basic page and Parent Unit

- [x] Expert
- [x] Forages
- [x] Map

### Views

- [x] expert_display
    - Cannot migrate: unknown
    - Manually created
- [x] experts
    - Cannot migrate: unknown
- [x] experts_by_species
    - [x] Cache updated
    - [x] Displays updated
- [x] forages
    - [x] Cache updated
    - [x] Displays updated
- [x] maps
    - [x] Cache updated
    - [x] Displays updated
- [ ] species_galleries
    - Cannot Migrate: Image Album content
- [x] species_grid
    - [x] Cache updated
    - [x] Displays updated
- [x] species_list
    - [x] Cache updated
    - [x] Displays updated
- [x] species_selection_tool
    - [x] Cache updated
    - [x] Displays updated
- [x] species_selection_tool_2
    - [x] Cache updated
    - [x] Displays updated
- [x] species_suitability_maps
    - [x] Cache updated
    - [x] Displays updated
- [x] species_expert_link
    - [x] Cache updated
    - [x] Displays updated
- [x] topical_index
  - Cannot migrate: OG references
  - Manually created.
